<?php

namespace App\Support\Movimentacoes;

use App\Models\Movimentacao;
use App\Models\UnidadeNegocio;

final class VendaCustoOperacionalHub
{
    public static function saidaFisicaEhHub(?int $idUnidadeNegocioEstoque): bool
    {
        if ($idUnidadeNegocioEstoque === null || $idUnidadeNegocioEstoque <= 0) {
            return false;
        }

        $unidade = UnidadeNegocio::query()->find($idUnidadeNegocioEstoque);

        return $unidade !== null && $unidade->is_hub;
    }

    public static function saidaFisicaEhHubUnidade(UnidadeNegocio $unidadePmDebito): bool
    {
        return $unidadePmDebito->is_hub;
    }

    public static function coEmbutidoNoCustoSaida(Movimentacao $venda): bool
    {
        return self::saidaFisicaEhHub(
            $venda->id_unidade_negocio_estoque !== null
                ? (int) $venda->id_unidade_negocio_estoque
                : null,
        );
    }

    public static function valorCoTotalDescontadoNaMargem(Movimentacao $venda): float
    {
        if (self::coEmbutidoNoCustoSaida($venda)) {
            return 0.0;
        }

        return round((float) $venda->valor_custo_operacional * (float) $venda->qtd_fruta_kg, 2);
    }

    public static function valorCustoSaidaEsperado(Movimentacao $venda): float
    {
        $base = round((float) $venda->preco_medio_fruta_kg * (float) $venda->qtd_fruta_kg, 2);
        if (! self::coEmbutidoNoCustoSaida($venda)) {
            return $base;
        }

        return round($base + (float) $venda->valor_custo_operacional * (float) $venda->qtd_fruta_kg, 2);
    }

    public static function divergeCustosHub(Movimentacao $venda): bool
    {
        if (! self::coEmbutidoNoCustoSaida($venda)) {
            return false;
        }

        $esperado = self::valorCustoSaidaEsperado($venda);
        $atual = round((float) $venda->valor_custo_saida, 2);

        return abs($esperado - $atual) > 0.009
            || trim((string) ($venda->observacao ?? '')) === '';
    }

    public static function observacaoCustoEmbutidoHub(
        UnidadeNegocio $unidadeFaturamento,
        float $valorCoKg,
        float $qtdKg,
    ): string {
        $coTotal = round($valorCoKg * $qtdKg, 2);

        return sprintf(
            'Saída física em unidade HUB. Custo operacional da unidade de faturamento (%s) de R$ %s/kg '
            .'(total R$ %s nesta quantidade) embutido no custo de saída. '
            .'O preço médio do saldo remanescente no HUB e o estoque do galpão não recebem este CO.',
            $unidadeFaturamento->nome,
            number_format($valorCoKg, 2, ',', '.'),
            number_format($coTotal, 2, ',', '.'),
        );
    }
}
