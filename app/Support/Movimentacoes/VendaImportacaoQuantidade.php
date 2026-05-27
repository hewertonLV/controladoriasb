<?php

namespace App\Support\Movimentacoes;

use App\Enums\FrutaUnidadeMedicao;
use App\Models\Fruta;

/**
 * Converte quantidade/UM da planilha de vendas para qtd_fruta_um do sistema.
 */
final class VendaImportacaoQuantidade
{
    /**
     * @return array{
     *     qtd_fruta_um: string,
     *     unidade_medicao_fruta: string,
     *     qtd_planilha: string,
     *     unidade_medicao_planilha: string
     * }|null
     */
    public static function normalizar(Fruta $fruta, string $qtdPlanilha, string $umPlanilha): ?array
    {
        $umFruta = (string) $fruta->unidade_medicao;
        $kgPorUm = (float) $fruta->kg_por_unidade_medicao;
        $qtd = (float) $qtdPlanilha;

        if ($umPlanilha === $umFruta) {
            return self::resultado($qtdPlanilha, $umPlanilha, $qtd, $umFruta);
        }

        if ($umPlanilha === FrutaUnidadeMedicao::KG->value && $umFruta !== FrutaUnidadeMedicao::KG->value) {
            if ($kgPorUm <= 0) {
                return null;
            }

            $qtdUm = round($qtd / $kgPorUm, 2);
            if ($qtdUm <= 0) {
                return null;
            }

            return self::resultado($qtdPlanilha, $umPlanilha, $qtdUm, $umFruta);
        }

        return null;
    }

    public static function mensagemErroUnidadeMedicao(string $umPlanilha, string $umFruta): string
    {
        if ($umPlanilha === FrutaUnidadeMedicao::KG->value && $umFruta !== FrutaUnidadeMedicao::KG->value) {
            return 'Quantidade em KG insuficiente para converter para a unidade de medição cadastrada da fruta ('.$umFruta.').';
        }

        return "Unidade de medição da planilha ({$umPlanilha}) difere da cadastrada para a fruta ({$umFruta}). Use a UM da fruta ou KG.";
    }

    /**
     * @return array{
     *     qtd_fruta_um: string,
     *     unidade_medicao_fruta: string,
     *     qtd_planilha: string,
     *     unidade_medicao_planilha: string
     * }
     */
    private static function resultado(string $qtdPlanilha, string $umPlanilha, float $qtdUm, string $umFruta): array
    {
        return [
            'qtd_planilha' => $qtdPlanilha,
            'unidade_medicao_planilha' => $umPlanilha,
            'qtd_fruta_um' => number_format($qtdUm, 2, '.', ''),
            'unidade_medicao_fruta' => $umFruta,
        ];
    }
}
