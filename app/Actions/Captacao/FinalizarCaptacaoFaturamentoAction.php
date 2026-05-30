<?php

namespace App\Actions\Captacao;

use App\Enums\CaptacaoFaturamentoDiaStatus;
use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoFaturamentoDia;
use App\Models\Captacao\CaptacaoLote;
use App\Models\User;
use App\Services\Captacao\CaptacaoLoteService;
use App\Services\Captacao\PedidoCaptacaoEstadoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FinalizarCaptacaoFaturamentoAction
{
    public function __construct(
        private readonly PedidoCaptacaoEstadoService $estadoCaptacao,
        private readonly CaptacaoLoteService $lotes,
    ) {}

    public function executar(string $dataReferencia, int $idUnidadeFaturamento, User $user): CaptacaoFaturamentoDia
    {
        $lotes = CaptacaoLote::query()
            ->whereDate('data_referencia', $dataReferencia)
            ->where('id_unidade_negocio_faturamento', $idUnidadeFaturamento)
            ->where('tipo', CaptacaoLoteTipo::CaptacaoPedidos->value)
            ->where('status', CaptacaoLoteStatus::CaptacaoEmAndamento->value)
            ->with(['carteira:id,nome', 'unidadeGalpao:id,nome', 'pedidos.cliente', 'pedidos.itens'])
            ->get();

        if ($lotes->isEmpty()) {
            throw ValidationException::withMessages([
                'data_referencia' => 'Nenhum lote de captação em andamento encontrado para esta data e faturamento.',
            ]);
        }

        foreach ($lotes as $lote) {
            $this->assertTodasLojasConcluidas($lote);
        }

        return DB::transaction(function () use ($dataReferencia, $idUnidadeFaturamento, $user, $lotes): CaptacaoFaturamentoDia {
            $dia = $this->lotes->resolverFaturamentoDia($dataReferencia, $idUnidadeFaturamento);

            if ($dia->status === CaptacaoFaturamentoDiaStatus::CaptacaoFaturamentoFinalizada) {
                foreach ($lotes as $lote) {
                    $this->lotes->transicionarStatus($lote, CaptacaoLoteStatus::CaptacaoConcluida);
                }

                $this->lotes->sincronizarLotesEmAndamentoQuandoDiaFinalizado($dataReferencia, $idUnidadeFaturamento);

                return $dia->refresh();
            }

            foreach ($lotes as $lote) {
                $this->lotes->transicionarStatus($lote, CaptacaoLoteStatus::CaptacaoConcluida);
            }

            $dia->update([
                'status' => CaptacaoFaturamentoDiaStatus::CaptacaoFaturamentoFinalizada,
                'finalizado_em' => now(),
                'finalizado_por_user_id' => $user->id,
            ]);

            return $dia->refresh();
        });
    }

    private function assertTodasLojasConcluidas(CaptacaoLote $lote): void
    {
        if ($this->estadoCaptacao->todasLojasElegiveisConcluidas($lote)) {
            return;
        }

        $pendentes = $this->estadoCaptacao->lojasComPedidoNaoConcluido($lote)
            ->take(5)
            ->map(fn ($c) => $c->fantasia ?: $c->razao_social)
            ->implode(', ');

        throw ValidationException::withMessages([
            'pedidos' => "Conclua a captação de todas as lojas da carteira «{$lote->carteira?->nome}» antes de finalizar. Pendentes: {$pendentes}.",
        ]);
    }
}
