<?php

namespace App\Actions\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Models\Captacao\CaptacaoLote;
use App\Services\Captacao\CaptacaoLoteService;
use Illuminate\Validation\ValidationException;

final class ConcluirEtapaFreteLoteAction
{
    public function __construct(
        private readonly CaptacaoLoteService $lotes,
    ) {}

    public function executar(CaptacaoLote $lote): CaptacaoLote
    {
        if ($lote->status !== CaptacaoLoteStatus::AguardandoVinculoFrete) {
            throw ValidationException::withMessages([
                'status' => 'O lote não está na etapa de frete.',
            ]);
        }

        return $this->lotes->transicionarStatus($lote, CaptacaoLoteStatus::TransferenciaFinalizada);
    }
}
