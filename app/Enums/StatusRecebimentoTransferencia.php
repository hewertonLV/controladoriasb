<?php

namespace App\Enums;

enum StatusRecebimentoTransferencia: string
{
    case CONFORME = 'CONFORME';
    case DIVERGENTE = 'DIVERGENTE';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
