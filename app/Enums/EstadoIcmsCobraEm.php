<?php

namespace App\Enums;

enum EstadoIcmsCobraEm: string
{
    case ENTRADA = 'ENTRADA';
    case SAIDA = 'SAIDA';
    case NENHUM = 'NENHUM';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
