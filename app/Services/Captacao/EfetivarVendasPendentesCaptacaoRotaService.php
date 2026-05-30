<?php

namespace App\Services\Captacao;

use App\Enums\StatusTransferenciaOperacional;
use App\Enums\VendaNotaStatusConclusao;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteFreteLinha;
use App\Models\Captacao\CaptacaoLoteMovimentacao;
use App\Models\Captacao\Pedido;
use App\Models\Movimentacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Models\VendaNota;
use App\Services\Movimentacoes\VendaMovimentacaoService;
use App\Support\Captacao\SaidaEstoqueFisicoCaptacaoService;
use Illuminate\Support\Facades\DB;

final class EfetivarVendasPendentesCaptacaoRotaService
{
    public function __construct(
        private readonly SaidaEstoqueFisicoCaptacaoService $saidaFisica,
        private readonly VendaMovimentacaoService $vendas,
        private readonly CaptacaoDemandaRotaService $demandasRota,
    ) {}

    public function tentarEfetivarPorTransferencia(int $transferenciaOrigemId, ?User $user = null): void
    {
        $vinculoTransferencia = CaptacaoLoteMovimentacao::query()
            ->where('tipo', CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
            ->where('transferencia_origem_id', $transferenciaOrigemId)
            ->first();

        if ($vinculoTransferencia === null || $vinculoTransferencia->id_captacao_rota === null) {
            return;
        }

        if (! $this->transferenciaEstaConforme($transferenciaOrigemId)) {
            return;
        }

        $lote = CaptacaoLote::query()->findOrFail($vinculoTransferencia->id_captacao_lote);
        $rotaId = (int) $vinculoTransferencia->id_captacao_rota;

        $vendasPendentes = CaptacaoLoteMovimentacao::query()
            ->with('vendaNota')
            ->where('id_captacao_lote', $lote->id)
            ->where('id_captacao_rota', $rotaId)
            ->where('tipo', CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA)
            ->whereNotNull('id_pedido')
            ->get();

        foreach ($vendasPendentes as $vinculoVenda) {
            $nota = $vinculoVenda->vendaNota;
            if ($nota === null || $nota->status_conclusao !== VendaNotaStatusConclusao::AguardandoTransferencia->value) {
                continue;
            }

            $pedido = Pedido::query()
                ->with(['itens.fruta', 'cliente'])
                ->find($vinculoVenda->id_pedido);

            if ($pedido === null || ! $this->todasTransferenciasDoPedidoConformes($lote, $rotaId, $pedido)) {
                continue;
            }

            $this->efetivarVendaPendente($lote, $pedido, $nota, $vinculoVenda, $user);
        }
    }

    private function transferenciaEstaConforme(int $transferenciaOrigemId): bool
    {
        return Movimentacao::query()
            ->where('transferencia_origem_id', $transferenciaOrigemId)
            ->where('status_registro', \App\Enums\MovimentacaoStatusRegistro::ATIVO->value)
            ->where('status_transferencia', StatusTransferenciaOperacional::RECEBIDA_CONFORME->value)
            ->exists();
    }

    private function todasTransferenciasDoPedidoConformes(CaptacaoLote $lote, int $rotaId, Pedido $pedido): bool
    {
        if (! $this->saidaFisica->pedidoExigeTransferenciaParaGalpao($pedido, $lote)) {
            return true;
        }

        $idOrigem = $this->saidaFisica->idSaidaEfetiva($pedido, $lote);
        $frutasPedido = $pedido->itens
            ->filter(static fn ($item) => (float) $item->quantidade > 0)
            ->pluck('id_fruta')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($frutasPedido === []) {
            return false;
        }

        $vinculo = CaptacaoLoteMovimentacao::query()
            ->with('linhas')
            ->where('id_captacao_lote', $lote->id)
            ->where('id_captacao_rota', $rotaId)
            ->where('tipo', CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
            ->where('id_unidade_negocio_origem', $idOrigem)
            ->first();

        if ($vinculo === null || $vinculo->transferencia_origem_id === null) {
            return false;
        }

        $frutasDemanda = $vinculo->linhas->pluck('id_fruta')->map(fn ($id) => (int) $id);
        if ($frutasDemanda->isEmpty() && $vinculo->id_fruta !== null) {
            $frutasDemanda = collect([(int) $vinculo->id_fruta]);
        }

        foreach ($frutasPedido as $idFruta) {
            if (! $frutasDemanda->contains($idFruta)) {
                return false;
            }
        }

        return $this->transferenciaEstaConforme((int) $vinculo->transferencia_origem_id);
    }

    private function efetivarVendaPendente(
        CaptacaoLote $lote,
        Pedido $pedido,
        VendaNota $nota,
        CaptacaoLoteMovimentacao $vinculo,
        ?User $user,
    ): void {
        if ($nota->movimentacoes()->where('status_registro', \App\Enums\MovimentacaoStatusRegistro::ATIVO->value)->exists()) {
            return;
        }

        $faturamento = UnidadeNegocio::query()->findOrFail($lote->id_unidade_negocio_faturamento);
        $galpao = UnidadeNegocio::query()->findOrFail($lote->id_unidade_negocio_galpao);
        $empresaFaturamento = $faturamento->registroCorporativo()->firstOrFail();
        $cliente = $pedido->cliente ?? \App\Models\Cliente::query()->findOrFail($pedido->id_cliente);
        $empresaCliente = $cliente->registroCorporativo()->firstOrFail();

        $fretesPorFruta = CaptacaoLoteFreteLinha::query()
            ->where('id_captacao_lote', $lote->id)
            ->pluck('id_frete', 'id_fruta');

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
            return;
        }

        $dataEmissao = $pedido->data_entrega ?? $lote->data_referencia->copy()->addDay();
        $primeiraFruta = (int) $pedido->itens->first()->id_fruta;
        $idFrete = $fretesPorFruta->get($primeiraFruta);

        DB::transaction(function () use ($nota, $empresaFaturamento, $empresaCliente, $faturamento, $galpao, $itens, $dataEmissao, $idFrete, $lote, $pedido, $user, $vinculo): void {
            $this->demandasRota->marcarVendaIniciada($vinculo);

            $this->vendas->concluirVendaAguardandoTransferencia($nota, [
                'numero_nf' => $nota->numero_nf,
                'id_empresa_origem' => $empresaFaturamento->id,
                'id_empresa_destino' => $empresaCliente->id,
                'id_unidade_negocio_centro_resultado' => $galpao->id,
                'id_unidade_negocio_estoque' => $galpao->id,
                'data_emissao' => $dataEmissao->format('Y-m-d'),
                'id_frete' => $idFrete,
                'observacao' => "Captação lote #{$lote->id} pedido cliente #{$pedido->id_cliente}",
                'itens' => $itens,
            ], $user);

            $this->demandasRota->marcarVendaConcluidaPorNota($nota->id);
        });
    }
}
