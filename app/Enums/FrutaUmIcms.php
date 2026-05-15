<?php

namespace App\Enums;

enum FrutaUmIcms: string
{
    case KG = 'KG';
    case UM = 'UM';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
