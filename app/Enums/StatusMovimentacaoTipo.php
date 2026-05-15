<?php

namespace App\Enums;

use App\Models\StatusMovimentacao;

/**
 * Espelha os IDs fixos de {@see StatusMovimentacao} (entrada/saída).
 */
enum StatusMovimentacaoTipo: int
{
    case Entrada = StatusMovimentacao::ID_ENTRADA;
    case Saida = StatusMovimentacao::ID_SAIDA;

    public function nomeLegivel(): string
    {
        return match ($this) {
            self::Entrada => 'Entrada',
            self::Saida => 'Saída',
        };
    }
}
