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

    /** Aba na matriz para baixar o arquivo TXT de transferência ao Cigan. */
    public function exibeAbaArquivoCiganTransferencia(): bool
    {
        return $this === self::TransferenciaCiganIniciada;
    }

    public function permiteEdicaoQuantidadeCaptacao(): bool
    {
        return $this === self::CaptacaoEmAndamento;
    }

    /** Vínculo de rota, ordem de carregamento e motorista (abertura até vendas finalizadas). */
    public function permiteEdicaoVinculoRota(): bool
    {
        return $this !== self::VendasFinalizadas;
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
            self::FaturamentoCiganIniciado,
            self::VendasFinalizadas,
        ], true);
    }

    /** Classe para destacar a linha na listagem de lotes (ver CSS em lotes/index). */
    public function classeLinhaListagem(): string
    {
        return 'captacao-lote-row captacao-lote-row--'.$this->varianteListagem();
    }

    public function badgeListagem(): string
    {
        return match ($this) {
            self::CaptacaoEmAndamento => 'bg-primary-subtle text-primary',
            self::AguardandoTransferenciaCigan,
            self::AguardandoVinculoFrete => 'bg-warning-subtle text-warning',
            self::TransferenciaCiganIniciada,
            self::FaturamentoCiganIniciado => 'bg-info-subtle text-info',
            self::TransferenciaFinalizada => 'bg-secondary-subtle text-secondary',
            self::VendasFinalizadas => 'bg-success-subtle text-success',
        };
    }

    private function varianteListagem(): string
    {
        return match ($this) {
            self::CaptacaoEmAndamento => 'captacao',
            self::AguardandoTransferenciaCigan,
            self::AguardandoVinculoFrete => 'aguardando',
            self::TransferenciaCiganIniciada,
            self::FaturamentoCiganIniciado => 'andamento',
            self::TransferenciaFinalizada => 'transferencia',
            self::VendasFinalizadas => 'finalizado',
        };
    }
}
