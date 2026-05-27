<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoLoteTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteFreteLinha;
use App\Models\Captacao\CaptacaoLoteMovimentacao;
use App\Models\Captacao\Pedido;
use App\Models\Cliente;
use App\Models\Movimentacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Models\VendaNota;
use App\Services\Movimentacoes\VendaMovimentacaoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class GerarVendasCaptacaoLoteService
{
    public function __construct(
        private readonly VendaMovimentacaoService $vendas,
    ) {}

    /**
     * @return list<int> ids de VendaNota
     */
    public function executar(CaptacaoLote $lote, ?User $user = null): array
    {
        if ($lote->tipo === CaptacaoLoteTipo::RomaneioManual) {
            throw ValidationException::withMessages([
                'tipo' => 'Solicitação de transferência não gera vendas.',
            ]);
        }

        $lote->load(['pedidos.itens.fruta', 'pedidos.cliente']);

        $faturamento = UnidadeNegocio::query()->findOrFail($lote->id_unidade_negocio_faturamento);
        $galpao = UnidadeNegocio::query()->findOrFail($lote->id_unidade_negocio_galpao);
        $empresaFaturamento = $faturamento->registroCorporativo()->firstOrFail();

        $fretesPorFruta = CaptacaoLoteFreteLinha::query()
            ->where('id_captacao_lote', $lote->id)
            ->pluck('id_frete', 'id_fruta');

        $notaIds = [];

        DB::transaction(function () use ($lote, $user, $faturamento, $galpao, $empresaFaturamento, $fretesPorFruta, &$notaIds): void {
            foreach ($lote->pedidos as $pedido) {
                if ($this->pedidoVendaJaSincronizada($lote, $pedido)) {
                    $notaExistente = $this->vendaNotaDoPedido($lote, $pedido);
                    if ($notaExistente !== null) {
                        $notaIds[] = $notaExistente->id;
                    }

                    continue;
                }

                $notaExistente = $this->vendaNotaDoPedido($lote, $pedido);
                if ($notaExistente !== null) {
                    $this->garantirVinculoVendaNota($lote, $notaExistente);
                    $notaIds[] = $notaExistente->id;

                    continue;
                }

                if ($pedido->itens->isEmpty()) {
                    continue;
                }

                $cliente = $pedido->cliente ?? Cliente::query()->findOrFail($pedido->id_cliente);
                $empresaCliente = $cliente->registroCorporativo()->firstOrFail();

                $itens = [];
                foreach ($pedido->itens as $item) {
                    $qtdUm = (float) $item->quantidade;
                    if ($qtdUm <= 0) {
                        continue;
                    }
                    $precoUm = (float) ($item->preco_venda ?? 0);
                    $itens[] = [
                        'id_fruta' => $item->id_fruta,
                        'qtd_fruta_um' => number_format($qtdUm, 2, '.', ''),
                        'valor_nf_total' => number_format(round($precoUm * $qtdUm, 2), 2, '.', ''),
                    ];
                }

                if ($itens === []) {
                    continue;
                }

                $dataEmissao = $pedido->data_entrega
                    ?? $lote->data_referencia->copy()->addDay();

                $primeiraFruta = (int) $pedido->itens->first()->id_fruta;
                $idFrete = $fretesPorFruta->get($primeiraFruta);

                $unidadeSaida = (int) ($pedido->id_unidade_negocio_saida_venda ?? $galpao->id);

                $resultado = $this->vendas->registrarVenda([
                    'numero_nf' => $this->numeroNfCaptacao($lote, $pedido->id_cliente),
                    'id_empresa_origem' => $empresaFaturamento->id,
                    'id_empresa_destino' => $empresaCliente->id,
                    'id_unidade_negocio_centro_resultado' => $galpao->id,
                    'id_unidade_negocio_estoque' => $unidadeSaida,
                    'data_emissao' => $dataEmissao->format('Y-m-d'),
                    'id_frete' => $idFrete,
                    'observacao' => "Captação lote #{$lote->id} pedido cliente #{$pedido->id_cliente}",
                    'itens' => $itens,
                ], $user);

                /** @var VendaNota $nota */
                $nota = $resultado['nota'];
                $notaIds[] = $nota->id;

                CaptacaoLoteMovimentacao::query()->create([
                    'id_captacao_lote' => $lote->id,
                    'tipo' => CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA,
                    'venda_nota_id' => $nota->id,
                ]);
            }
        });

        $notaIds = array_values(array_unique($notaIds));

        if ($notaIds === [] && $this->pedidosComQuantidade($lote)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'vendas' => 'Nenhuma venda foi gerada. Verifique itens com quantidade e preço.',
            ]);
        }

        return $notaIds;
    }

    /**
     * Resumo por loja: itens captados vs movimentações de venda geradas no SB.
     *
     * @return list<array{
     *     id_cliente: int,
     *     loja_nome: string,
     *     itens_captados: int,
     *     movimentacoes: int,
     *     numero_nf: string|null,
     *     completo: bool,
     * }>
     */
    public function resumoVendasLote(CaptacaoLote $lote): array
    {
        $lote->loadMissing(['pedidos.itens', 'pedidos.cliente']);

        $resumo = [];

        foreach ($lote->pedidos as $pedido) {
            $itensCaptados = $pedido->itens->filter(static fn ($item) => (float) $item->quantidade > 0)->count();
            if ($itensCaptados === 0) {
                continue;
            }

            $nota = $this->vendaNotaDoPedido($lote, $pedido);
            $movimentacoes = $nota === null
                ? 0
                : Movimentacao::query()
                    ->where('venda_nota_id', $nota->id)
                    ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
                    ->count();

            $resumo[] = [
                'id_cliente' => $pedido->id_cliente,
                'loja_nome' => $pedido->cliente?->fantasia ?: $pedido->cliente?->razao_social ?: "Cliente #{$pedido->id_cliente}",
                'itens_captados' => $itensCaptados,
                'movimentacoes' => $movimentacoes,
                'numero_nf' => $nota?->numero_nf,
                'completo' => $nota !== null && $movimentacoes === $itensCaptados,
            ];
        }

        return $resumo;
    }

    public function possuiVendasPendentes(CaptacaoLote $lote): bool
    {
        foreach ($this->resumoVendasLote($lote) as $linha) {
            if (! $linha['completo']) {
                return true;
            }
        }

        return false;
    }

    private function pedidoVendaJaSincronizada(CaptacaoLote $lote, Pedido $pedido): bool
    {
        $itensCaptados = $pedido->itens->filter(static fn ($item) => (float) $item->quantidade > 0)->count();
        if ($itensCaptados === 0) {
            return true;
        }

        $nota = $this->vendaNotaDoPedido($lote, $pedido);
        if ($nota === null) {
            return false;
        }

        $vinculo = CaptacaoLoteMovimentacao::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('tipo', CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA)
            ->where('venda_nota_id', $nota->id)
            ->exists();

        if (! $vinculo) {
            return false;
        }

        $movimentacoes = Movimentacao::query()
            ->where('venda_nota_id', $nota->id)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->count();

        return $movimentacoes === $itensCaptados;
    }

    private function vendaNotaDoPedido(CaptacaoLote $lote, Pedido $pedido): ?VendaNota
    {
        return VendaNota::query()
            ->where('numero_nf', $this->numeroNfCaptacao($lote, $pedido->id_cliente))
            ->first();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Pedido>
     */
    private function pedidosComQuantidade(CaptacaoLote $lote)
    {
        return $lote->pedidos->filter(function (Pedido $pedido): bool {
            return $pedido->itens->contains(static fn ($item) => (float) $item->quantidade > 0);
        });
    }

    private function garantirVinculoVendaNota(CaptacaoLote $lote, VendaNota $nota): void
    {
        CaptacaoLoteMovimentacao::query()->firstOrCreate([
            'id_captacao_lote' => $lote->id,
            'tipo' => CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA,
            'venda_nota_id' => $nota->id,
        ]);
    }

    private function numeroNfCaptacao(CaptacaoLote $lote, int $idCliente): string
    {
        return sprintf(
            'CAP-%s-%d-%d',
            $lote->data_referencia->format('Ymd'),
            $lote->id,
            $idCliente,
        );
    }
}
