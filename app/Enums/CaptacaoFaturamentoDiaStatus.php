<?php

namespace App\Enums;

enum CaptacaoFaturamentoDiaStatus: string
{
    case CaptacaoAberta = 'CAPTACAO_ABERTA';
    case CaptacaoFaturamentoFinalizada = 'CAPTACAO_FATURAMENTO_FINALIZADA';

    public function label(): string
    {
        return match ($this) {
            self::CaptacaoAberta => 'Captação aberta',
            self::CaptacaoFaturamentoFinalizada => 'Captação finalizada',
        };
    }
}
