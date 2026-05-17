<?php

namespace App\Services\Movimentacoes;

/**
 * Mantém compatibilidade com os fluxos de transferência que solicitam recálculo de frete.
 */
final class ReconciliacaoTransferenciaService
{
    public function __construct(
        private readonly FreteRateioMovimentacaoService $freteRateio,
    ) {}

    public function recalcularRateioFreteParaTransferencias(int $idFrete): void
    {
        $this->freteRateio->recalcular($idFrete);
    }
}
