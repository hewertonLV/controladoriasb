<?php

namespace App\Actions\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Models\Captacao\CaptacaoLote;
use App\Services\Captacao\CaptacaoLoteService;
use App\Services\Captacao\EfetivarTransferenciasGerenciaisLoteService;
use Illuminate\Validation\ValidationException;

final class ValidarTransferenciasGerenciaisLoteAction
{
    public function __construct(
        private readonly CaptacaoLoteService $lotes,
        private readonly EfetivarTransferenciasGerenciaisLoteService $transferenciasGerenciais,
    ) {}

    public function executar(CaptacaoLote $lote): CaptacaoLote
    {
        if ($lote->status !== CaptacaoLoteStatus::TransferenciaCiganIniciada) {
            throw ValidationException::withMessages([
                'status' => 'Inicie a transferência Cigan antes de validar.',
            ]);
        }

        $this->transferenciasGerenciais->executar($lote);

        return $this->lotes->transicionarStatus($lote, CaptacaoLoteStatus::AguardandoVinculoFrete);
    }
}
