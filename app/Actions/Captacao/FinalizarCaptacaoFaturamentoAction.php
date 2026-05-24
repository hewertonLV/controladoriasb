<?php

namespace App\Actions\Captacao;

use App\Enums\CaptacaoFaturamentoDiaStatus;
use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoFaturamentoDia;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\Pedido;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FinalizarCaptacaoFaturamentoAction
{
    public function executar(string $dataReferencia, int $idUnidadeFaturamento, User $user): CaptacaoFaturamentoDia
    {
        $lotes = CaptacaoLote::query()
            ->whereDate('data_referencia', $dataReferencia)
            ->where('id_unidade_negocio_faturamento', $idUnidadeFaturamento)
            ->where('tipo', CaptacaoLoteTipo::CaptacaoPedidos->value)
            ->where('status', CaptacaoLoteStatus::CaptacaoEmAndamento->value)
            ->with(['unidadeGalpao:id,nome', 'pedidos.cliente', 'pedidos.itens'])
            ->get();

        if ($lotes->isEmpty()) {
            throw ValidationException::withMessages([
                'data_referencia' => 'Nenhum lote de captação em andamento encontrado para esta data e faturamento.',
            ]);
        }

        foreach ($lotes as $lote) {
            $this->assertPedidosComQuantidadeTemRota($lote);
        }

        return DB::transaction(function () use ($dataReferencia, $idUnidadeFaturamento, $user, $lotes): CaptacaoFaturamentoDia {
            $dia = CaptacaoFaturamentoDia::query()->firstOrCreate(
                [
                    'data_referencia' => $dataReferencia,
                    'id_unidade_negocio_faturamento' => $idUnidadeFaturamento,
                ],
                [
                    'status' => CaptacaoFaturamentoDiaStatus::CaptacaoAberta,
                ],
            );

            if ($dia->status === CaptacaoFaturamentoDiaStatus::CaptacaoFaturamentoFinalizada) {
                throw ValidationException::withMessages([
                    'data_referencia' => 'Captação já finalizada para esta data e faturamento.',
                ]);
            }

            foreach ($lotes as $lote) {
                $lote->update(['status' => CaptacaoLoteStatus::AguardandoTransferenciaCigan]);
            }

            $dia->update([
                'status' => CaptacaoFaturamentoDiaStatus::CaptacaoFaturamentoFinalizada,
                'finalizado_em' => now(),
                'finalizado_por_user_id' => $user->id,
            ]);

            return $dia->refresh();
        });
    }

    private function assertPedidosComQuantidadeTemRota(CaptacaoLote $lote): void
    {
        foreach ($lote->pedidos as $pedido) {
            if (! $this->pedidoTemQuantidadeCaptada($pedido)) {
                continue;
            }

            if ($pedido->id_captacao_rota === null) {
                $nomeLoja = $pedido->cliente?->fantasia ?: $pedido->cliente?->razao_social ?: "#{$pedido->id_cliente}";

                throw ValidationException::withMessages([
                    'pedidos' => "A loja «{$nomeLoja}» (galpão {$lote->unidadeGalpao?->nome}) tem quantidade na matriz, mas está sem rota. Vincule a rota no pedido antes de finalizar.",
                ]);
            }
        }
    }

    private function pedidoTemQuantidadeCaptada(Pedido $pedido): bool
    {
        foreach ($pedido->itens as $item) {
            if ((float) $item->quantidade > 0) {
                return true;
            }
        }

        return false;
    }
}
