<?php

namespace App\Enums;

enum CaptacaoDemandaStatus: string
{
    case Aberto = 'ABERTO';
    case Iniciado = 'INICIADO';
    case Concluido = 'CONCLUIDO';

    public function label(): string
    {
        return match ($this) {
            self::Aberto => 'Aberto',
            self::Iniciado => 'Iniciado',
            self::Concluido => 'Concluído',
        };
    }
}
