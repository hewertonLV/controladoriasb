<?php

namespace App\Actions\Captacao;

use App\Models\Captacao\CaptacaoLote;
use App\Models\User;
use App\Services\Captacao\AvancarEtapaVinculoRotasCaptacaoLoteService;

/** @deprecated Use upload NF de venda + {@see ConcluirVinculoRotasCaptacaoLoteAction}. */
final class FinalizarVendasLoteAction
{
    public function __construct(
        private readonly AvancarEtapaVinculoRotasCaptacaoLoteService $avancarVinculoRotas,
    ) {}

    public function executar(CaptacaoLote $lote, ?User $user = null): CaptacaoLote
    {
        unset($user);

        return $this->avancarVinculoRotas->concluirManualmente($lote);
    }
}
