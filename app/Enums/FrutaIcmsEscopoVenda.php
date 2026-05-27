<?php

namespace App\Enums;

enum FrutaIcmsEscopoVenda: string
{
    case DENTRO_ESTADO = 'DENTRO_ESTADO';
    case FORA_ESTADO = 'FORA_ESTADO';

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
            self::DENTRO_ESTADO => 'Dentro do estado',
            self::FORA_ESTADO => 'Fora do estado',
        };
    }
}
