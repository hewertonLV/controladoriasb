<?php

namespace App\Actions\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Models\Captacao\CaptacaoLote;
use App\Models\User;
use App\Services\Captacao\CaptacaoLoteService;
use App\Services\Captacao\GerarArquivoCiganService;
use Illuminate\Validation\ValidationException;

final class IniciarTransferenciaCiganAction
{
    public function __construct(
        private readonly CaptacaoLoteService $lotes,
        private readonly GerarArquivoCiganService $cigan,
    ) {}

    public function executar(CaptacaoLote $lote, User $user): CaptacaoLote
    {
        if ($lote->status !== CaptacaoLoteStatus::AguardandoTransferenciaCigan) {
            throw ValidationException::withMessages([
                'status' => 'O lote não está aguardando transferência.',
            ]);
        }

        $this->cigan->gerarTransferencia($lote, $user);

        return $this->lotes->transicionarStatus($lote, CaptacaoLoteStatus::TransferenciaCiganIniciada);
    }
}
