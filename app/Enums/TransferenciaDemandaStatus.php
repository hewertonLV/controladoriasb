<?php

namespace App\Enums;

enum TransferenciaDemandaStatus: string
{
    case DemandaCriada = 'DEMANDA_CRIADA';
    case Iniciado = 'INICIADO';
    case VincularFrete = 'VINCULAR_FRETE';
    case Concluido = 'CONCLUIDO';

    public function label(): string
    {
        return match ($this) {
            self::DemandaCriada => 'Demanda criada',
            self::Iniciado => 'Iniciado',
            self::VincularFrete => 'Vincular frete',
            self::Concluido => 'Concluído',
        };
    }
}
