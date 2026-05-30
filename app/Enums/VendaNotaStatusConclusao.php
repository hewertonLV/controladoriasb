<?php

namespace App\Enums;

enum VendaNotaStatusConclusao: string
{
    case Pendente = 'PENDENTE';
    case AguardandoTransferencia = 'AGUARDANDO_TRANSFERENCIA';
    case Concluida = 'CONCLUIDA';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::AguardandoTransferencia => 'Aguardando transferência',
            self::Concluida => 'Concluída',
        };
    }
}
