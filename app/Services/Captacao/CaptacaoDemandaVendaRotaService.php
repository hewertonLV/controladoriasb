<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoDemandaStatus;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\VendaNotaStatusConclusao;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteFreteLinha;
use App\Models\Captacao\CaptacaoLoteMovimentacao;
use App\Models\Captacao\CaptacaoLoteMovimentacaoLinha;
use App\Models\Captacao\Pedido;
use App\Models\Cliente;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Models\VendaNota;
use App\Services\Movimentacoes\VendaMovimentacaoService;
use App\Support\Captacao\SaidaEstoqueFisicoCaptacaoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CaptacaoDemandaVendaRotaService
{
    public function __construct(
        private readonly SaidaEstoqueFisicoCaptacaoService $saidaFisica,
        private readonly CaptacaoDemandaEstoqueService $estoque,
        private readonly VendaMovimentacaoService $vendas,
        private readonly CaptacaoDemandaRotaService $demandasRota,
        private readonly GerarVendasCaptacaoLoteService $gerarVendas,
        private readonly CiganEdiNfVendaGerador $cigamVendas,
    ) {}

    /**
     * @return array{pode: bool, linhas: list<array<string, mixed>>}
     */
    public function validarEstoqueEfetivar(CaptacaoLoteMovimentacao $vinculo): array
    {
        if ($vinculo->id_pedido !== null) {
            return $this->validarEstoquePedido($this->resolverPedidoLegado($vinculo), $vinculo);
        }

        $lote = CaptacaoLote::query()->findOrFail($vinculo->id_captacao_lote);
        $vinculo->loadMissing(['linhas.fruta']);

        $faltas = [];
        $pode = true;

        foreach ($this->pedidosDaDemanda($vinculo) as $pedido) {
            $validacao = $this->validarEstoquePedido($pedido, $vinculo, $lote);
            if (! $validacao['pode']) {
                $pode = false;
            }
            foreach ($validacao['linhas'] as $linha) {
                if (! $linha['ok']) {
                    $linha['loja_nome'] = $pedido->cliente?->fantasia
                        ?: $pedido->cliente?->razao_social
                        ?: "Cliente #{$pedido->id_cliente}";
                    $faltas[] = $linha;
                }
            }
        }

        return ['pode' => $pode, 'linhas' => $faltas];
    }

    public function gerarArquivoCigam(CaptacaoLoteMovimentacao $demanda): string
    {
        $this->assertDemandaVenda($demanda);

        $conteudo = $this->cigamVendas->gerarPorDemanda($demanda);

        return $this->cigamVendas->paraIso88591($conteudo);
    }

    public function efetivar(CaptacaoLoteMovimentacao $vinculo, ?User $user = null): CaptacaoLoteMovimentacao
    {
        $this->assertVendaAberta($vinculo);

        if ($vinculo->id_pedido !== null) {
            return $this->efetivarDemandaLegadoPorPedido($vinculo, $user);
        }

        $validacao = $this->validarEstoqueEfetivar($vinculo);
        if (! $validacao['pode']) {
            $mensagens = array_map(
                fn (array $linha): string => ($linha['loja_nome'] ?? 'Loja')." · {$linha['fruta_nome']}: faltam {$linha['qtd_falta']} (disponível {$linha['qtd_disponivel']})",
                $validacao['linhas'],
            );

            throw ValidationException::withMessages([
                'estoque' => $mensagens,
                'faltas' => $validacao['linhas'],
            ]);
        }

        $lote = CaptacaoLote::query()->findOrFail($vinculo->id_captacao_lote);
        $pedidos = $this->pedidosDaDemanda($vinculo);

        return DB::transaction(function () use ($vinculo, $lote, $pedidos, $user): CaptacaoLoteMovimentacao {
            $this->demandasRota->marcarVendaIniciada($vinculo);

            foreach ($pedidos as $pedido) {
                $this->efetivarPedidoNaDemanda($vinculo, $lote, $pedido, $user);
            }

            $vinculo->update(['status_demanda' => CaptacaoDemandaStatus::Concluido->value]);

            return $vinculo->fresh();
        });
    }

    /**
     * @return Collection<int, Pedido>
     */
    private function pedidosDaDemanda(CaptacaoLoteMovimentacao $vinculo): Collection
    {
        $vinculo->loadMissing(['linhas.pedido.cliente', 'linhas.pedido.itens.fruta']);

        $idsPedido = $vinculo->linhas
            ->pluck('id_pedido')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($idsPedido->isEmpty()) {
            throw ValidationException::withMessages(['demanda' => 'Demanda de venda sem linhas de romaneio.']);
        }

        return Pedido::query()
            ->with(['itens.fruta', 'cliente'])
            ->whereIn('id', $idsPedido->all())
            ->orderBy('ordem_carregamento')
            ->orderBy('id')
            ->get();
    }

    private function efetivarPedidoNaDemanda(
        CaptacaoLoteMovimentacao $vinculo,
        CaptacaoLote $lote,
        Pedido $pedido,
        ?User $user,
    ): void {
        $nota = $this->resolverOuCriarNotaPendente($lote, $pedido, $vinculo);

        if ($nota->movimentacoes()->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)->exists()) {
            return;
        }

        $linhasPedido = $vinculo->linhas->where('id_pedido', $pedido->id);
        $itens = $this->montarItensDasLinhas($linhasPedido);
        if ($itens === []) {
            return;
        }

        $faturamento = UnidadeNegocio::query()->findOrFail($lote->id_unidade_negocio_faturamento);
        $galpao = UnidadeNegocio::query()->findOrFail($lote->id_unidade_negocio_galpao);
        $empresaFaturamento = $faturamento->registroCorporativo()->firstOrFail();
        $cliente = $pedido->cliente ?? Cliente::query()->findOrFail($pedido->id_cliente);
        $empresaCliente = $cliente->registroCorporativo()->firstOrFail();
        $unidadeSaida = $this->saidaFisica->idSaidaEfetiva($pedido, $lote);

        $dataEmissao = $pedido->data_entrega ?? $lote->data_referencia->copy()->addDay();
        $primeiraFruta = (int) ($linhasPedido->first()?->id_fruta ?? $pedido->itens->first()->id_fruta);
        $idFrete = CaptacaoLoteFreteLinha::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('id_fruta', $primeiraFruta)
            ->value('id_frete');

        $this->vendas->concluirVendaAguardandoTransferencia($nota, [
            'numero_nf' => $nota->numero_nf,
            'id_empresa_origem' => $empresaFaturamento->id,
            'id_empresa_destino' => $empresaCliente->id,
            'id_unidade_negocio_centro_resultado' => $galpao->id,
            'id_unidade_negocio_estoque' => $unidadeSaida,
            'data_emissao' => $dataEmissao->format('Y-m-d'),
            'id_frete' => $idFrete,
            'observacao' => "Captação lote #{$lote->id} pedido cliente #{$pedido->id_cliente}",
            'itens' => $itens,
        ], $user);
    }

    private function resolverOuCriarNotaPendente(
        CaptacaoLote $lote,
        Pedido $pedido,
        CaptacaoLoteMovimentacao $vinculo,
    ): VendaNota {
        $nota = VendaNota::query()
            ->where('numero_nf', $this->gerarVendas->numeroNfCaptacaoPublico($lote, $pedido->id_cliente))
            ->first();

        if ($nota !== null) {
            return $nota;
        }

        $faturamento = UnidadeNegocio::query()->findOrFail($lote->id_unidade_negocio_faturamento);
        $galpao = UnidadeNegocio::query()->findOrFail($lote->id_unidade_negocio_galpao);
        $empresaFaturamento = $faturamento->registroCorporativo()->firstOrFail();
        $cliente = $pedido->cliente ?? Cliente::query()->findOrFail($pedido->id_cliente);
        $empresaCliente = $cliente->registroCorporativo()->firstOrFail();
        $dataEmissao = $pedido->data_entrega ?? $lote->data_referencia->copy()->addDay();
        $rotaNome = $vinculo->captacaoRota?->nome ?? 'rota';

        return VendaNota::query()->create([
            'numero_nf' => $this->gerarVendas->numeroNfCaptacaoPublico($lote, $pedido->id_cliente),
            'id_empresa_origem' => $empresaFaturamento->id,
            'id_empresa_destino' => $empresaCliente->id,
            'id_unidade_negocio_faturamento' => $faturamento->id,
            'id_unidade_negocio_centro_resultado' => $galpao->id,
            'data_emissao' => $dataEmissao,
            'valor_total_nf' => '0.00',
            'status_registro' => MovimentacaoStatusRegistro::ATIVO->value,
            'status_conclusao' => VendaNotaStatusConclusao::Pendente->value,
            'observacao' => "Captação lote #{$lote->id} rota «{$rotaNome}» — demanda de venda",
        ]);
    }

    /**
     * @param  Collection<int, CaptacaoLoteMovimentacaoLinha>  $linhas
     * @return list<array{id_fruta: int, qtd_fruta_um: string, valor_nf_total: string}>
     */
    private function montarItensDasLinhas(Collection $linhas): array
    {
        $itens = [];

        foreach ($linhas as $linha) {
            $qtdUm = (float) $linha->qtd_um;
            if ($qtdUm <= 0) {
                continue;
            }
            $precoUm = (float) ($linha->preco_venda ?? 0);
            $itens[] = [
                'id_fruta' => (int) $linha->id_fruta,
                'qtd_fruta_um' => number_format($qtdUm, 2, '.', ''),
                'valor_nf_total' => number_format(round($precoUm * $qtdUm, 2), 2, '.', ''),
            ];
        }

        return $itens;
    }

    /**
     * @return array{pode: bool, linhas: list<array<string, mixed>>}
     */
    private function validarEstoquePedido(
        Pedido $pedido,
        CaptacaoLoteMovimentacao $vinculo,
        ?CaptacaoLote $lote = null,
    ): array {
        $lote ??= CaptacaoLote::query()->findOrFail($vinculo->id_captacao_lote);
        $idSaida = $this->saidaFisica->idSaidaEfetiva($pedido, $lote);

        $linhas = [];
        if ($vinculo->id_pedido !== null) {
            foreach ($pedido->itens as $item) {
                $qtd = (float) $item->quantidade;
                if ($qtd <= 0) {
                    continue;
                }
                $linhas[] = ['id_fruta' => (int) $item->id_fruta, 'qtd_um' => $qtd];
            }
        } else {
            foreach ($vinculo->linhas->where('id_pedido', $pedido->id) as $linhaDemanda) {
                $qtd = (float) $linhaDemanda->qtd_um;
                if ($qtd <= 0) {
                    continue;
                }
                $linhas[] = ['id_fruta' => (int) $linhaDemanda->id_fruta, 'qtd_um' => $qtd];
            }
        }

        return $this->estoque->validarLinhas($idSaida, $linhas);
    }

    private function efetivarDemandaLegadoPorPedido(CaptacaoLoteMovimentacao $vinculo, ?User $user): CaptacaoLoteMovimentacao
    {
        $validacao = $this->validarEstoqueEfetivar($vinculo);
        if (! $validacao['pode']) {
            $mensagens = array_map(
                fn (array $linha): string => "{$linha['fruta_nome']}: faltam {$linha['qtd_falta']} na unidade de saída (disponível {$linha['qtd_disponivel']})",
                array_filter($validacao['linhas'], static fn (array $l): bool => ! $l['ok']),
            );

            throw ValidationException::withMessages([
                'estoque' => $mensagens,
                'faltas' => $validacao['linhas'],
            ]);
        }

        $pedido = $this->resolverPedidoLegado($vinculo);
        $lote = CaptacaoLote::query()->findOrFail($vinculo->id_captacao_lote);
        $nota = VendaNota::query()->findOrFail($vinculo->venda_nota_id);

        if ($nota->movimentacoes()->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)->exists()) {
            throw ValidationException::withMessages(['venda' => 'Venda já efetivada.']);
        }

        return DB::transaction(function () use ($vinculo, $nota, $lote, $pedido, $user): CaptacaoLoteMovimentacao {
            $this->demandasRota->marcarVendaIniciada($vinculo);
            $this->efetivarPedidoNaDemanda($vinculo, $lote, $pedido, $user);
            $this->demandasRota->marcarVendaConcluidaPorNota($nota->id);

            return $vinculo->fresh();
        });
    }

    private function resolverPedidoLegado(CaptacaoLoteMovimentacao $vinculo): Pedido
    {
        if ($vinculo->id_pedido === null) {
            throw ValidationException::withMessages(['demanda' => 'Pedido não vinculado à demanda de venda.']);
        }

        return Pedido::query()
            ->with(['itens.fruta', 'cliente'])
            ->findOrFail($vinculo->id_pedido);
    }

    private function assertDemandaVenda(CaptacaoLoteMovimentacao $demanda): void
    {
        if ($demanda->tipo !== CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA) {
            throw ValidationException::withMessages(['demanda' => 'Demanda inválida.']);
        }
    }

    private function assertVendaAberta(CaptacaoLoteMovimentacao $vinculo): void
    {
        $this->assertDemandaVenda($vinculo);

        $status = CaptacaoDemandaStatus::tryFrom((string) $vinculo->status_demanda)
            ?? CaptacaoDemandaStatus::Aberto;

        if ($status === CaptacaoDemandaStatus::Concluido) {
            throw ValidationException::withMessages(['demanda' => 'Venda já concluída.']);
        }
    }
}
