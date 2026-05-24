<?php

namespace App\Enums;

enum PedidoOrigem: string
{
    case Web = 'WEB';
    case App = 'APP';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::App => 'App',
        };
    }
}
