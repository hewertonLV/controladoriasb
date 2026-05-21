<?php

namespace App\Services\Movimentacoes;

use App\Contracts\Movimentacoes\ReprocessaSaidasVendaOrigem;
use App\Models\Movimentacao;
use Illuminate\Support\Facades\DB;

final class ReplayEstoqueVendaService implements ReprocessaSaidasVendaOrigem
{
    public function __construct(
        private readonly ReplayLinhaTempoEstoqueService $linhaTempo,
    ) {}

    public function reprocessarSaidasVendaNaUnidadeOrigem(int $idUnidadeNegocio, int $idFruta, ?int $movimentacaoInicioId = null): void
    {
        DB::transaction(function () use ($idUnidadeNegocio, $idFruta, $movimentacaoInicioId): void {
            if ($movimentacaoInicioId !== null) {
                $saidaCancelada = Movimentacao::query()->whereKey($movimentacaoInicioId)->firstOrFail();
                $this->linhaTempo->reprocessarUnidadeFrutaAposCancelamentoSaida($idUnidadeNegocio, $idFruta, $saidaCancelada);

                return;
            }

            $this->linhaTempo->reprocessarUnidadeFruta($idUnidadeNegocio, $idFruta);
        });
    }
}
