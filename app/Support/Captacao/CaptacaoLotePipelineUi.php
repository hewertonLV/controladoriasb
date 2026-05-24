<?php

namespace App\Support\Captacao;

use App\Enums\CaptacaoLoteAcaoPipeline;
use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoLote;

final class CaptacaoLotePipelineUi
{
    public static function proximaAcao(CaptacaoLote $lote): ?CaptacaoLoteAcaoPipeline
    {
        if ($lote->tipo === CaptacaoLoteTipo::RomaneioManual) {
            return match ($lote->status) {
                CaptacaoLoteStatus::CaptacaoEmAndamento => CaptacaoLoteAcaoPipeline::ConfirmarRomaneioManual,
                CaptacaoLoteStatus::AguardandoTransferenciaCigan => CaptacaoLoteAcaoPipeline::IniciarTransferencia,
                CaptacaoLoteStatus::TransferenciaCiganIniciada => CaptacaoLoteAcaoPipeline::ConcluirTransferenciaManual,
                default => null,
            };
        }

        return match ($lote->status) {
            CaptacaoLoteStatus::CaptacaoEmAndamento => CaptacaoLoteAcaoPipeline::FinalizarCaptacaoFaturamento,
            CaptacaoLoteStatus::AguardandoTransferenciaCigan => CaptacaoLoteAcaoPipeline::IniciarTransferencia,
            CaptacaoLoteStatus::TransferenciaCiganIniciada => CaptacaoLoteAcaoPipeline::ValidarTransferencias,
            CaptacaoLoteStatus::AguardandoVinculoFrete => CaptacaoLoteAcaoPipeline::ConcluirFrete,
            CaptacaoLoteStatus::TransferenciaFinalizada => CaptacaoLoteAcaoPipeline::IniciarFaturamento,
            CaptacaoLoteStatus::FaturamentoCiganIniciado => CaptacaoLoteAcaoPipeline::FinalizarVendas,
            default => null,
        };
    }
}
