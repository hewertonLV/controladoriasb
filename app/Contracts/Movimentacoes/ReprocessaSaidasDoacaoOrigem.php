<?php

namespace App\Contracts\Movimentacoes;

/**
 * Reprocessa saídas de doação ativas na unidade de origem (reconstrói {@see \App\Models\MovimentacaoEstoque} e saldos).
 */
interface ReprocessaSaidasDoacaoOrigem
{
    /**
     * Recalcula a cadeia de saídas da categoria doação para a unidade + fruta informadas.
     */
    public function reprocessarSaidasDoacaoNaUnidadeOrigem(int $idUnidadeNegocio, int $idFruta): void;
}
