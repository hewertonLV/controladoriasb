<?php

namespace App\Contracts\Movimentacoes;

interface ReprocessaSaidasVendaOrigem
{
    public function reprocessarSaidasVendaNaUnidadeOrigem(int $idUnidadeNegocio, int $idFruta, ?int $movimentacaoInicioId = null): void;
}
