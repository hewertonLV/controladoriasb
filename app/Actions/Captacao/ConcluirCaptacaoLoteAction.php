<?php

namespace App\Actions\Captacao;

use App\Models\Captacao\CaptacaoLote;
use App\Services\Captacao\ConcluirCaptacaoLoteService;

final class ConcluirCaptacaoLoteAction
{
    public function __construct(
        private readonly ConcluirCaptacaoLoteService $concluir,
    ) {}

    public function executar(CaptacaoLote $lote): CaptacaoLote
    {
        return $this->concluir->concluir($lote);
    }
}
