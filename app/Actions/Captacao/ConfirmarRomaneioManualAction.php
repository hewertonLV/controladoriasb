<?php

namespace App\Actions\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoRomaneioManualLinha;
use App\Services\Captacao\CaptacaoLoteService;
use Illuminate\Validation\ValidationException;

final class ConfirmarRomaneioManualAction
{
    public function __construct(
        private readonly CaptacaoLoteService $lotes,
    ) {}

    public function executar(CaptacaoLote $lote): CaptacaoLote
    {
        if ($lote->tipo !== CaptacaoLoteTipo::RomaneioManual) {
            throw ValidationException::withMessages([
                'tipo' => 'Este lote não é uma solicitação de transferência.',
            ]);
        }

        if ($lote->status !== CaptacaoLoteStatus::CaptacaoEmAndamento) {
            throw ValidationException::withMessages([
                'status' => 'A solicitação de transferência já foi confirmada ou está em outro status.',
            ]);
        }

        if (! CaptacaoRomaneioManualLinha::query()->where('id_captacao_lote', $lote->id)->exists()) {
            throw ValidationException::withMessages([
                'linhas' => 'Informe ao menos uma linha de fruta.',
            ]);
        }

        return $this->lotes->transicionarStatus($lote, CaptacaoLoteStatus::AguardandoTransferenciaCigan);
    }
}
