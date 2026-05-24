<?php

namespace App\Enums;

enum CaptacaoLoteAcaoPipeline: string
{
    case FinalizarCaptacaoFaturamento = 'finalizar_captacao_faturamento';
    case ConfirmarRomaneioManual = 'confirmar_romaneio_manual';
    case IniciarTransferencia = 'iniciar_transferencia';
    case ValidarTransferencias = 'validar_transferencias';
    case VincularFrete = 'vincular_frete';
    case ConcluirFrete = 'concluir_frete';
    case ConcluirTransferenciaManual = 'concluir_transferencia_manual';
    case IniciarFaturamento = 'iniciar_faturamento';
    case FinalizarVendas = 'finalizar_vendas';

    public function label(): string
    {
        return match ($this) {
            self::FinalizarCaptacaoFaturamento => 'Finalizar captação (faturamento)',
            self::ConfirmarRomaneioManual => 'Fechar romaneio',
            self::IniciarTransferencia => 'Iniciar transferência',
            self::ValidarTransferencias => 'Validar transferências',
            self::VincularFrete => 'Vincular frete',
            self::ConcluirFrete => 'Concluir etapa de frete',
            self::ConcluirTransferenciaManual => 'Concluir transferência',
            self::IniciarFaturamento => 'Iniciar faturamento Cigan',
            self::FinalizarVendas => 'Finalizar vendas SB',
        };
    }

    public function permission(): string
    {
        return match ($this) {
            self::FinalizarCaptacaoFaturamento => Permissions::CAPTACAO_FATURAMENTO_FINALIZAR,
            self::ConfirmarRomaneioManual => Permissions::CAPTACAO_ROMANEIO_MANUAL,
            self::IniciarTransferencia => Permissions::CAPTACAO_LOTE_TRANSFERENCIA_INICIAR,
            self::ValidarTransferencias => Permissions::CAPTACAO_LOTE_TRANSFERENCIA_VALIDAR,
            self::VincularFrete => Permissions::CAPTACAO_LOTE_FRETE_VINCULAR,
            self::ConcluirFrete => Permissions::CAPTACAO_LOTE_FRETE_CONCLUIR,
            self::ConcluirTransferenciaManual => Permissions::CAPTACAO_LOTE_TRANSFERENCIA_VALIDAR,
            self::IniciarFaturamento => Permissions::CAPTACAO_LOTE_FATURAMENTO_INICIAR,
            self::FinalizarVendas => Permissions::CAPTACAO_LOTE_VENDA_FINALIZAR,
        };
    }

    public function variant(): string
    {
        return match ($this) {
            self::FinalizarCaptacaoFaturamento, self::ConfirmarRomaneioManual => 'warning',
            self::IniciarTransferencia, self::ValidarTransferencias, self::ConcluirTransferenciaManual => 'info',
            self::VincularFrete => 'soft-warning',
            self::ConcluirFrete => 'secondary',
            self::IniciarFaturamento => 'soft-success',
            self::FinalizarVendas => 'success',
        };
    }
}
