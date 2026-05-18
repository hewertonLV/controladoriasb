<?php

namespace App\Services\Movimentacoes;

use App\Contracts\Movimentacoes\ReprocessaEntradasDevolucaoDestino;
use Illuminate\Support\Facades\DB;

final class ReplayEstoqueDevolucaoService implements ReprocessaEntradasDevolucaoDestino
{
    public function __construct(
        private readonly ReplayLinhaTempoEstoqueService $linhaTempo,
    ) {}

    public function reprocessarEntradasDevolucaoNaUnidadeDestino(int $idUnidadeNegocio, int $idFruta, ?int $movimentacaoInicioId = null): void
    {
        DB::transaction(function () use ($idUnidadeNegocio, $idFruta): void {
            $this->linhaTempo->reprocessarUnidadeFruta($idUnidadeNegocio, $idFruta);
        });
    }
}
