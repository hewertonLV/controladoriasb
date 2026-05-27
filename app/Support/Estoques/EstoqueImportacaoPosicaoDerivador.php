<?php

namespace App\Support\Estoques;

/**
 * Converte linha da planilha (qtd em UM + preço total) em posição de estoque (kg e R$/kg).
 */
final class EstoqueImportacaoPosicaoDerivador
{
    /**
     * @return array{
     *     qtd_fruta_um: string,
     *     valor_total: string,
     *     qtd_fruta_kg: string,
     *     preco_medio_kg: string,
     *     preco_medio_um: string,
     *     kg_por_unidade_medicao: string
     * }
     */
    public static function derivar(float $kgPorUnidadeMedicao, float $qtdUm, float $valorTotal): array
    {
        $kgPorUm = max(0.0, round($kgPorUnidadeMedicao, 2));
        $qtdUm = round($qtdUm, 2);
        $valorTotal = round($valorTotal, 2);

        $qtdKg = round($qtdUm * $kgPorUm, 2);
        $precoKg = abs($qtdKg) >= 0.005
            ? round($valorTotal / $qtdKg, 2)
            : 0.0;
        $precoUm = round($precoKg * $kgPorUm, 2);

        return [
            'qtd_fruta_um' => self::formatar($qtdUm),
            'valor_total' => self::formatar($valorTotal),
            'qtd_fruta_kg' => self::formatar($qtdKg),
            'preco_medio_kg' => self::formatar($precoKg),
            'preco_medio_um' => self::formatar($precoUm),
            'kg_por_unidade_medicao' => self::formatar($kgPorUm),
        ];
    }

    private static function formatar(float $valor): string
    {
        return number_format($valor, 2, '.', '');
    }
}
