<?php

namespace App\Enums;

enum FrutaUnidadeMedicao: string
{
    case CAIXA = 'CAIXA';
    case PACOTE = 'PACOTE';
    case UNIDADE = 'UNIDADE';
    case SACO = 'SACO';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
