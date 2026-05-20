<?php

namespace App\Enums;

enum FrutaProcedencia: string
{
    case NACIONAL = 'NACIONAL';
    case INTERNACIONAL = 'INTERNACIONAL';

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
            self::NACIONAL => 'Nacional',
            self::INTERNACIONAL => 'Internacional',
        };
    }
}
