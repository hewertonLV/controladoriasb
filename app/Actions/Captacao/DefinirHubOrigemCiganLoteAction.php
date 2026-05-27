<?php

namespace App\Actions\Captacao;

use App\Models\Captacao\CaptacaoLote;

final class DefinirHubOrigemCiganLoteAction
{
    public function executar(CaptacaoLote $lote, int $idUnidadeHubOrigem): CaptacaoLote
    {
        $lote->id_unidade_negocio_hub_origem = $idUnidadeHubOrigem;
        $lote->save();

        return $lote->fresh(['unidadeHubOrigem', 'unidadeGalpao', 'unidadeFaturamento']);
    }
}
