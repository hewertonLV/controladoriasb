<?php

namespace App\Contracts\Movimentacoes;

/**
 * Reprocessa saídas de descarte ativas na unidade de origem.
 */
interface ReprocessaSaidasDescarteOrigem
{
    public function reprocessarSaidasDescarteNaUnidadeOrigem(int $idUnidadeNegocio, int $idFruta): void;
}
