<?php

namespace App\Enums;

enum FrutaIcmsOperacao: string
{
    case ENTRADA = 'ENTRADA';
    case SAIDA = 'SAIDA';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::ENTRADA => 'Entrada',
            self::SAIDA => 'Saída',
        };
    }
}
