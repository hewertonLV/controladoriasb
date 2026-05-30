<?php

namespace App\Enums;

enum Roles: string
{
    case PROGRAMADOR = 'Programador';
    case ADMINISTRADOR = 'Administrador';
    case CONTROLADORIA = 'Controladoria';
    case HUB = 'Hub';
    case UNIDADE_COMERCIAL = 'Unidade Comercial';
    case LOGISTICA = 'Logística';
    case CONSULTA = 'Consulta';
    case VENDEDOR = 'Vendedor';

    /**
     * Retorna a lista completa de roles do sistema.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }
}
