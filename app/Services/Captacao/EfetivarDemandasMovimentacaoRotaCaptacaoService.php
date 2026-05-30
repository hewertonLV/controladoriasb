<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoDemandaStatus;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteMovimentacao;
use App\Models\Captacao\CaptacaoLoteMovimentacaoLinha;
use App\Models\Captacao\CaptacaoRota;
use App\Models\Captacao\Pedido;
use App\Support\Captacao\SaidaEstoqueFisicoCaptacaoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EfetivarDemandasMovimentacaoRotaCaptacaoService
{
    public function __construct(
        private readonly SaidaEstoqueFisicoCaptacaoService $saidaFisica,
        private readonly GerarVendasCaptacaoLoteService $gerarVendas,
    ) {}

    public function executar(CaptacaoLote $lote, CaptacaoRota $rota): void
    {
        $lote->loadMissing(['pedidos.itens.fruta', 'pedidos.cliente']);

        $pedidosRota = $lote->pedidos
            ->filter(fn (Pedido $pedido): bool => (int) $pedido->id_captacao_rota === (int) $rota->id
                && $this->pedidoTemQuantidade($pedido));

        if ($pedidosRota->isEmpty()) {
            return;
        }

        $pedidosComTransferencia = $pedidosRota->filter(
            fn (Pedido $pedido): bool => $this->saidaFisica->pedidoExigeTransferenciaParaGalpao($pedido, $lote),
        );

        DB::transaction(function () use ($lote, $rota, $pedidosComTransferencia, $pedidosRota): void {
            $this->registrarDemandiasTransferencia($lote, $rota, $pedidosComTransferencia);
            $this->gerarVendas->registrarDemandaVendaRota($lote, $rota, $pedidosRota);
        });
    }

    /**
     * @param  Collection<int, Pedido>  $pedidosComTransferencia
     */
    private function registrarDemandiasTransferencia(
        CaptacaoLote $lote,
        CaptacaoRota $rota,
        Collection $pedidosComTransferencia,
    ): void {
        /** @var array<int, array<int, float>> $frutasPorOrigem */
        $frutasPorOrigem = [];

        foreach ($pedidosComTransferencia as $pedido) {
            $idOrigem = $this->saidaFisica->idSaidaEfetiva($pedido, $lote);

            foreach ($pedido->itens as $item) {
                $qtdUm = (float) $item->quantidade;
                if ($qtdUm <= 0) {
                    continue;
                }

                $idFruta = (int) $item->id_fruta;
                $frutasPorOrigem[$idOrigem][$idFruta] = ($frutasPorOrigem[$idOrigem][$idFruta] ?? 0.0) + $qtdUm;
            }
        }

        $origensAtivas = array_keys($frutasPorOrigem);

        $demandasExistentes = CaptacaoLoteMovimentacao::query()
            ->with('linhas')
            ->where('id_captacao_lote', $lote->id)
            ->where('id_captacao_rota', $rota->id)
            ->where('tipo', CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
            ->get();

        foreach ($demandasExistentes as $demanda) {
            $origem = (int) ($demanda->id_unidade_negocio_origem ?? 0);
            if ($origem <= 0 || ! in_array($origem, $origensAtivas, true)) {
                $demanda->delete();

                continue;
            }

            $this->sincronizarLinhasDemanda($demanda, $frutasPorOrigem[$origem]);
            unset($frutasPorOrigem[$origem]);
        }

        foreach ($frutasPorOrigem as $idOrigem => $frutas) {
            if ($frutas === []) {
                continue;
            }

            $demanda = CaptacaoLoteMovimentacao::query()->create([
                'id_captacao_lote' => $lote->id,
                'id_captacao_rota' => $rota->id,
                'tipo' => CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA,
                'id_unidade_negocio_origem' => $idOrigem,
                'status_demanda' => CaptacaoDemandaStatus::Aberto->value,
            ]);

            $this->sincronizarLinhasDemanda($demanda, $frutas);
        }
    }

    /**
     * @param  array<int, float>  $frutas
     */
    private function sincronizarLinhasDemanda(CaptacaoLoteMovimentacao $demanda, array $frutas): void
    {
        $frutas = array_filter(
            $frutas,
            static fn (float $qtd): bool => round($qtd, 3) > 0,
        );

        $idsFruta = array_map('intval', array_keys($frutas));

        if ($idsFruta !== []) {
            $demanda->linhas()
                ->whereNull('id_pedido')
                ->whereNotIn('id_fruta', $idsFruta)
                ->delete();
        } else {
            $demanda->linhas()->whereNull('id_pedido')->delete();
        }

        foreach ($frutas as $idFruta => $qtdUm) {
            $linha = CaptacaoLoteMovimentacaoLinha::withTrashed()
                ->where('id_captacao_lote_movimentacao', $demanda->id)
                ->whereNull('id_pedido')
                ->where('id_fruta', (int) $idFruta)
                ->first();

            if ($linha !== null) {
                if ($linha->trashed()) {
                    $linha->restore();
                }
                $linha->update(['qtd_um' => round($qtdUm, 3)]);

                continue;
            }

            CaptacaoLoteMovimentacaoLinha::query()->create([
                'id_captacao_lote_movimentacao' => $demanda->id,
                'id_fruta' => (int) $idFruta,
                'qtd_um' => round($qtdUm, 3),
            ]);
        }

        $demanda->forceFill([
            'id_fruta' => null,
            'qtd_um' => null,
        ])->saveQuietly();
    }

    private function pedidoTemQuantidade(Pedido $pedido): bool
    {
        return $pedido->itens->contains(static fn ($item) => (float) $item->quantidade > 0);
    }
}
