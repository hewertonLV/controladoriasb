<?php

namespace App\Contracts\Movimentacoes;

/**
 * Reprocessa estoque no destino após alterações na linha de compras (replay).
 *
 * Contrato extraído para permitir substituição em testes (ex.: simular falha transacional).
 */
interface ReprocessaEstoqueDestinoCompra
{
    public function reprocessarEstoqueDestinoUnidadeFruta(int $idUnidadeNegocio, int $idFruta): void;
}
