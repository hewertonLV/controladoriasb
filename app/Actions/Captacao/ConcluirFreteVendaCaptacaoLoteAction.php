<?php

namespace App\Actions\Captacao;

use App\Models\Captacao\CaptacaoLote;
use App\Services\Captacao\AvancarEtapaFreteVendaCaptacaoLoteService;

final class ConcluirFreteVendaCaptacaoLoteAction
{
    public function __construct(
        private readonly AvancarEtapaFreteVendaCaptacaoLoteService $avancarEtapa,
    ) {}

    public function executar(CaptacaoLote $lote): CaptacaoLote
    {
        return $this->avancarEtapa->concluirManualmente($lote);
    }
}
