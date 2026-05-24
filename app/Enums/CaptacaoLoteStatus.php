<?php

namespace App\Enums;

enum CaptacaoLoteStatus: string
{
    case CaptacaoEmAndamento = 'CAPTACAO_EM_ANDAMENTO';
    case AguardandoTransferenciaCigan = 'AGUARDANDO_TRANSFERENCIA_CIGAN';
    case TransferenciaCiganIniciada = 'TRANSFERENCIA_CIGAN_INICIADA';
    case AguardandoVinculoFrete = 'AGUARDANDO_VINCULO_FRETE';
    case TransferenciaFinalizada = 'TRANSFERENCIA_FINALIZADA';
    case FaturamentoCiganIniciado = 'FATURAMENTO_CIGAN_INICIADO';
    case VendasFinalizadas = 'VENDAS_FINALIZADAS';

    public function label(): string
    {
        return match ($this) {
            self::CaptacaoEmAndamento => 'Captação em andamento',
            self::AguardandoTransferenciaCigan => 'Aguardando transferência (Cigan)',
            self::TransferenciaCiganIniciada => 'Transferência Cigan iniciada',
            self::AguardandoVinculoFrete => 'Aguardando vínculo de frete',
            self::TransferenciaFinalizada => 'Transferência finalizada',
            self::FaturamentoCiganIniciado => 'Faturamento Cigan iniciado',
            self::VendasFinalizadas => 'Vendas finalizadas',
        };
    }

    public function permiteEdicaoQuantidadeCaptacao(): bool
    {
        return $this === self::CaptacaoEmAndamento;
    }

    public function permiteEdicaoQuantidadeAposFinalizarFaturamento(): bool
    {
        return in_array($this, [
            self::AguardandoTransferenciaCigan,
        ], true);
    }

    public function permiteEdicaoPreco(): bool
    {
        return ! in_array($this, [
            self::TransferenciaFinalizada,
            self::FaturamentoCiganIniciado,
            self::VendasFinalizadas,
        ], true);
    }
}
