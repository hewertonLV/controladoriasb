<?php

namespace App\Enums;

enum CaptacaoLoteStatus: string
{
    case CaptacaoEmAndamento = 'CAPTACAO_EM_ANDAMENTO';
    case CaptacaoConcluida = 'CAPTACAO_CONCLUIDA';
    case AguardandoTransferenciaCigan = 'AGUARDANDO_TRANSFERENCIA_CIGAN';
    case TransferenciaCiganIniciada = 'TRANSFERENCIA_CIGAN_INICIADA';
    case SaidaEstoqueFisico = 'SAIDA_ESTOQUE_FISICO';
    case AguardandoVinculoFrete = 'AGUARDANDO_VINCULO_FRETE';
    case TransferenciaFinalizada = 'TRANSFERENCIA_FINALIZADA';
    case FaturamentoCiganIniciado = 'FATURAMENTO_CIGAN_INICIADO';
    case VincularRotasNosPedidos = 'VINCULAR_ROTAS_NOS_PEDIDOS';
    case VincularFreteVenda = 'VINCULAR_FRETE_VENDA';
    case VendasFinalizadas = 'VENDAS_FINALIZADAS';

    public function label(): string
    {
        return match ($this) {
            self::CaptacaoEmAndamento => 'Captação em andamento',
            self::CaptacaoConcluida => 'Captação concluída',
            self::AguardandoTransferenciaCigan => 'Aguardando transferência (Cigam)',
            self::TransferenciaCiganIniciada => 'Transferência Cigam iniciada',
            self::SaidaEstoqueFisico => 'Saída estoque físico (SB Controladoria)',
            self::AguardandoVinculoFrete => 'Aguardando vínculo de frete',
            self::TransferenciaFinalizada => 'Transferência finalizada',
            self::FaturamentoCiganIniciado => 'Faturamento Cigam iniciado',
            self::VincularRotasNosPedidos => 'Vincular rotas e carregamento',
            self::VincularFreteVenda => 'Vincular frete venda',
            self::VendasFinalizadas => 'Vendas finalizadas',
        };
    }

    /** Aba na matriz: TXT de transferência e NF enviada após importação no Cigam. */
    public function exibeAbaArquivoCiganTransferencia(): bool
    {
        return in_array($this, [
            self::TransferenciaCiganIniciada,
            self::SaidaEstoqueFisico,
            self::AguardandoVinculoFrete,
        ], true);
    }

    public function exibeAbaSaidaEstoqueFisico(): bool
    {
        return $this === self::SaidaEstoqueFisico;
    }

    /** Aba na matriz: TXT de vendas (faturamento → loja). */
    public function exibeAbaArquivoCiganVendas(): bool
    {
        return in_array($this, [
            self::FaturamentoCiganIniciado,
            self::VincularRotasNosPedidos,
            self::VincularFreteVenda,
            self::VendasFinalizadas,
        ], true);
    }

    /** Aba Arquivo Cigam visível (transferência ou vendas). */
    public function exibeAbaArquivoCigan(): bool
    {
        return $this->exibeAbaArquivoCiganTransferencia()
            || $this->exibeAbaArquivoCiganVendas();
    }

    /** Aba na matriz: vínculo opcional de frete nas transferências HUB × CD. */
    public function exibeAbaFreteHub(): bool
    {
        return $this === self::AguardandoVinculoFrete;
    }

    /** @deprecated Use exibeAbaFreteHub() */
    public function exibeAbaFreteLote(): bool
    {
        return $this->exibeAbaFreteHub();
    }

    /** Aba na matriz: vínculo opcional de frete nas vendas por loja. */
    public function exibeAbaFreteVendas(): bool
    {
        return in_array($this, [
            self::VincularFreteVenda,
            self::VendasFinalizadas,
        ], true);
    }

    /** Vínculo/alteração de frete de vendas na matriz (após concluir etapa, só administrador). */
    public function permiteEdicaoFreteVenda(): bool
    {
        return $this === self::VincularFreteVenda;
    }

    public function permiteUploadNfTransferenciaCigan(): bool
    {
        return $this === self::TransferenciaCiganIniciada;
    }

    public function permiteUploadNfVendaCigan(): bool
    {
        return $this === self::FaturamentoCiganIniciado;
    }

    public function permiteEdicaoQuantidadeCaptacao(): bool
    {
        return $this === self::CaptacaoEmAndamento;
    }

    /** Vínculo de rota, ordem de carregamento e motorista (até concluir rotas). */
    public function permiteEdicaoVinculoRota(): bool
    {
        return ! in_array($this, [
            self::CaptacaoConcluida,
            self::VincularFreteVenda,
            self::VendasFinalizadas,
        ], true);
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
            self::CaptacaoConcluida,
            self::FaturamentoCiganIniciado,
            self::VincularRotasNosPedidos,
            self::VincularFreteVenda,
            self::VendasFinalizadas,
        ], true);
    }

    public function captacaoOperacionalEncerrada(): bool
    {
        return $this === self::CaptacaoConcluida;
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
            self::CaptacaoConcluida => 'bg-success-subtle text-success',
            self::AguardandoTransferenciaCigan,
            self::AguardandoVinculoFrete => 'bg-warning-subtle text-warning',
            self::TransferenciaCiganIniciada,
            self::SaidaEstoqueFisico,
            self::FaturamentoCiganIniciado => 'bg-info-subtle text-info',
            self::VincularRotasNosPedidos,
            self::VincularFreteVenda => 'bg-warning-subtle text-warning',
            self::TransferenciaFinalizada => 'bg-secondary-subtle text-secondary',
            self::VendasFinalizadas => 'bg-success-subtle text-success',
        };
    }

    private function varianteListagem(): string
    {
        return match ($this) {
            self::CaptacaoEmAndamento => 'captacao',
            self::CaptacaoConcluida => 'finalizado',
            self::AguardandoTransferenciaCigan,
            self::AguardandoVinculoFrete => 'aguardando',
            self::TransferenciaCiganIniciada,
            self::SaidaEstoqueFisico,
            self::FaturamentoCiganIniciado => 'andamento',
            self::VincularRotasNosPedidos,
            self::VincularFreteVenda => 'aguardando',
            self::TransferenciaFinalizada => 'transferencia',
            self::VendasFinalizadas => 'finalizado',
        };
    }
}
