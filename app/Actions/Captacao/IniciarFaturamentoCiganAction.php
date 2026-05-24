<?php

namespace App\Actions\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoLote;
use App\Models\User;
use App\Services\Captacao\CaptacaoLoteService;
use App\Services\Captacao\GerarArquivoCiganService;
use Illuminate\Validation\ValidationException;

final class IniciarFaturamentoCiganAction
{
    public function __construct(
        private readonly CaptacaoLoteService $lotes,
        private readonly GerarArquivoCiganService $cigan,
    ) {}

    public function executar(CaptacaoLote $lote, User $user): CaptacaoLote
    {
        if ($lote->tipo === CaptacaoLoteTipo::RomaneioManual) {
            throw ValidationException::withMessages([
                'tipo' => 'Romaneio manual não possui etapa de faturamento.',
            ]);
        }

        if ($lote->status !== CaptacaoLoteStatus::TransferenciaFinalizada) {
            throw ValidationException::withMessages([
                'status' => 'Conclua a etapa de frete antes do faturamento.',
            ]);
        }

        $this->cigan->gerarVendas($lote, $user);

        return $this->lotes->transicionarStatus($lote, CaptacaoLoteStatus::FaturamentoCiganIniciado);
    }
}
