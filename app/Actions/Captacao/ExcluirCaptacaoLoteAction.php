<?php

namespace App\Actions\Captacao;

use App\Models\Captacao\CaptacaoLote;
use App\Services\Captacao\ExcluirCaptacaoLoteService;

final class ExcluirCaptacaoLoteAction
{
    public function __construct(
        private readonly ExcluirCaptacaoLoteService $exclusao,
    ) {}

    public function executar(CaptacaoLote $lote): void
    {
        $this->exclusao->excluir($lote);
    }
}
