<?php

namespace App\Enums;

/**
 * Fluxo operacional da transferência (independente de {@see MovimentacaoStatusRegistro}).
 */
enum StatusTransferenciaOperacional: string
{
    case PENDENTE_RECEBIMENTO = 'PENDENTE_RECEBIMENTO';
    case RECEBIDA_CONFORME = 'RECEBIDA_CONFORME';
    case RECEBIDA_DIVERGENTE = 'RECEBIDA_DIVERGENTE';
    case REENVIADA = 'REENVIADA';
    case CANCELADA = 'CANCELADA';
    case FINALIZADA = 'FINALIZADA';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
