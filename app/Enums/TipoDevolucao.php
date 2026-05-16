<?php

namespace App\Enums;

enum TipoDevolucao: string
{
    case COM_RETORNO_ESTOQUE = 'COM_RETORNO_ESTOQUE';
    case SEM_RETORNO_ESTOQUE = 'SEM_RETORNO_ESTOQUE';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $tipo): string => $tipo->value, self::cases());
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::COM_RETORNO_ESTOQUE => 'Com retorno ao estoque',
            self::SEM_RETORNO_ESTOQUE => 'Sem retorno ao estoque',
        };
    }
}
