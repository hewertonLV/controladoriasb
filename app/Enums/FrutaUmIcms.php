<?php

namespace App\Enums;

enum FrutaUmIcms: string
{
    case KG = 'KG';
    case UM = 'UM';
    case PCT = 'PCT';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    /**
     * @return list<string>
     */
    public static function valoresEntrada(): array
    {
        return [self::KG->value, self::UM->value];
    }

    /**
     * @return list<string>
     */
    public static function valoresSaida(): array
    {
        return self::values();
    }

    public function isPercentual(): bool
    {
        return $this === self::PCT;
    }
}
