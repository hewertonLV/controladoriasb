<?php

namespace App\Enums;

enum CaptacaoLoteTipo: string
{
    case CaptacaoPedidos = 'CAPTACAO_PEDIDOS';
    case RomaneioManual = 'ROMANEIO_MANUAL';

    public function label(): string
    {
        return match ($this) {
            self::CaptacaoPedidos => 'Captação de pedidos',
            self::RomaneioManual => 'Solicitar transferência',
        };
    }

    public function possuiEtapaJefferson(): bool
    {
        return $this === self::CaptacaoPedidos;
    }
}
