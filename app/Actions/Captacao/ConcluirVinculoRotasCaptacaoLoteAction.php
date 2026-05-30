<?php

namespace App\Actions\Captacao;

use App\Models\Captacao\CaptacaoLote;
use App\Services\Captacao\AvancarEtapaVinculoRotasCaptacaoLoteService;

final class ConcluirVinculoRotasCaptacaoLoteAction
{
    public function __construct(
        private readonly AvancarEtapaVinculoRotasCaptacaoLoteService $avancarEtapa,
    ) {}

    public function executar(CaptacaoLote $lote): CaptacaoLote
    {
        return $this->avancarEtapa->concluirManualmente($lote);
    }
}
