<?php

namespace App\Enums;

enum OlhoDeDeusAlertaTipo: string
{
    case VendaPrecoAbaixoCustoKg = 'venda_preco_abaixo_custo_kg';
    case VendaPrecoAbaixoCustoUm = 'venda_preco_abaixo_custo_um';
    case FreteKgElevado = 'frete_kg_elevado';
    case RentabilidadeVendaNegativa = 'rentabilidade_venda_negativa';
    case VendaAbaixoCustoTotal = 'venda_abaixo_custo_total';
    case RentabilidadeLojaNegativa = 'rentabilidade_loja_negativa';
    case DevolucaoResultadoNegativo = 'devolucao_resultado_negativo';
    case DescartePerdaElevada = 'descarte_perda_elevada';
    case DoacaoPerdaElevada = 'doacao_perda_elevada';

    public function titulo(): string
    {
        return match ($this) {
            self::VendaPrecoAbaixoCustoKg => 'Preço de venda (kg) abaixo do custo',
            self::VendaPrecoAbaixoCustoUm => 'Preço de venda (UM) abaixo do custo',
            self::FreteKgElevado => 'Frete por kg acima do limite',
            self::RentabilidadeVendaNegativa => 'Rentabilidade negativa na venda',
            self::VendaAbaixoCustoTotal => 'Venda com valor NF abaixo do custo de saída',
            self::RentabilidadeLojaNegativa => 'Rentabilidade da loja negativa no mês',
            self::DevolucaoResultadoNegativo => 'Devolução com resultado negativo',
            self::DescartePerdaElevada => 'Descarte com perda elevada',
            self::DoacaoPerdaElevada => 'Doação com valor elevado',
        };
    }

    public function severidade(): string
    {
        return match ($this) {
            self::DoacaoPerdaElevada => 'warning',
            self::DescartePerdaElevada => 'warning',
            default => 'danger',
        };
    }
}
