<?php

namespace App\Services\Movimentacoes;

use App\Contracts\Movimentacoes\ReprocessaSaidasDescarteOrigem;
use Illuminate\Support\Facades\DB;

final class ReplayEstoqueDescarteService implements ReprocessaSaidasDescarteOrigem
{
    public function __construct(
        private readonly ReplayLinhaTempoEstoqueService $linhaTempo,
    ) {}

    public function reprocessarSaidasDescarteNaUnidadeOrigem(int $idUnidadeNegocio, int $idFruta): void
    {
        DB::transaction(function () use ($idUnidadeNegocio, $idFruta): void {
            $this->linhaTempo->reprocessarUnidadeFruta($idUnidadeNegocio, $idFruta);
        });
    }
}
