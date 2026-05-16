<?php

namespace App\Contracts\Movimentacoes;

/**
 * Reprocessa saídas de transferência na unidade de origem (replay).
 *
 * Contrato extraído para permitir substituição em testes (ex.: simular falha transacional).
 */
interface ReprocessaSaidasTransferenciaOrigem
{
    public function reprocessarSaidasTransferenciaNaUnidadeOrigem(int $idUnidadeNegocio, int $idFruta): void;
}
