<?php

namespace App\Contracts\Movimentacoes;

interface ReprocessaEntradasDevolucaoDestino
{
    public function reprocessarEntradasDevolucaoNaUnidadeDestino(int $idUnidadeNegocio, int $idFruta, ?int $movimentacaoInicioId = null): void;
}
