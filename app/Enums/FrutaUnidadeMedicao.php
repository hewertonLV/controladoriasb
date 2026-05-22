<?php

namespace App\Enums;

enum FrutaUnidadeMedicao: string
{
    case CAIXA = 'CAIXA';
    case PACOTE = 'PACOTE';
    case PCT = 'PCT';
    case BDJ = 'BDJ';
    case KG = 'KG';
    case UNIDADE = 'UNIDADE';
    case SACO = 'SACO';

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
            self::PCT => 'PCT — Pacote',
            self::BDJ => 'BDJ — Bandeja',
            self::KG => 'KG — Quilograma',
            default => $this->value,
        };
    }

    public function casasDecimaisKg(): int
    {
        return match ($this) {
            self::PCT, self::KG => 3,
            default => 2,
        };
    }
}
