<?php

namespace App\Enums;

enum FreteStatusSituacao: string
{
    case ABERTA = 'ABERTA';
    case ENCERRADA = 'ENCERRADA';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }
}
