<?php

namespace App\Support\Movimentacoes;

use App\Models\Movimentacao;

/**
 * Valor econômico da baixa em doação (custo médio × kg), independente de campos de NF.
 */
final class DoacaoValorEconomico
{
    public static function valorTotalMovimentacao(Movimentacao $m): float
    {
        $attrs = $m->getAttributes();
        $bruto = $attrs['valor_total_movimentacao'] ?? null;
        $t = $bruto !== null && $bruto !== '' ? (float) $bruto : 0.0;
        if ($t > 0) {
            return round($t, 2);
        }

        return round((float) $m->preco_medio_fruta_kg * (float) $m->qtd_fruta_kg, 2);
    }
}
