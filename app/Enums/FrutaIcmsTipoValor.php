<?php

namespace App\Enums;

enum FrutaIcmsTipoValor: string
{
    case VALOR_POR_KG = 'VALOR_POR_KG';
    case PERCENTUAL = 'PERCENTUAL';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
