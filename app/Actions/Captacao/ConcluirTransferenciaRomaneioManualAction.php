<?php

namespace App\Actions\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoLote;
use App\Services\Captacao\CaptacaoLoteService;
use App\Services\Captacao\EfetivarTransferenciasGerenciaisLoteService;
use Illuminate\Validation\ValidationException;

final class ConcluirTransferenciaRomaneioManualAction
{
    public function __construct(
        private readonly CaptacaoLoteService $lotes,
        private readonly EfetivarTransferenciasGerenciaisLoteService $transferenciasGerenciais,
    ) {}

    public function executar(CaptacaoLote $lote): CaptacaoLote
    {
        if ($lote->tipo !== CaptacaoLoteTipo::RomaneioManual) {
            throw ValidationException::withMessages([
                'tipo' => 'Ação disponível apenas para solicitação de transferência.',
            ]);
        }

        if ($lote->status !== CaptacaoLoteStatus::TransferenciaCiganIniciada) {
            throw ValidationException::withMessages([
                'status' => 'Inicie a transferência Cigan antes de concluir.',
            ]);
        }

        $this->transferenciasGerenciais->executar($lote);

        return $this->lotes->transicionarStatus($lote, CaptacaoLoteStatus::TransferenciaFinalizada);
    }
}
