<?php

namespace App\Services\Captacao;

use App\Models\Captacao\PedidoItem;
use App\Models\Estoque;
use App\Models\Fruta;

final class CaptacaoPrecificacaoService
{
    /**
     * Custo de referência na UM da fruta (ADR-0073): PM por unidade de medição do estoque do galpão.
     */
    public function custoReferenciaPorUm(int $idUnidadeGalpao, Fruta $fruta): ?string
    {
        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $idUnidadeGalpao)
            ->where('id_fruta', $fruta->id)
            ->where('ativo_unico', 1)
            ->first();

        if ($estoque === null) {
            return null;
        }

        if ((float) $estoque->qtd_fruta_um <= 0 && (float) $estoque->qtd_fruta_kg <= 0) {
            return null;
        }

        $pmUm = (float) $estoque->preco_medio_um;
        if ($pmUm > 0) {
            return number_format($pmUm, 4, '.', '');
        }

        $pmKg = (float) $estoque->preco_medio_kg;
        $kgPorUm = $this->kgPorUnidadeMedicao($fruta);
        if ($pmKg > 0 && $kgPorUm > 0) {
            return number_format($pmKg * $kgPorUm, 4, '.', '');
        }

        return null;
    }

    public function percentualDescontoCliente(?float $descontoNf): float
    {
        return min(100.0, max(0.0, (float) ($descontoNf ?? 0)));
    }

  /**
     * Preço efetivo após desconto NF do cliente (mesma regra de VendaMovimentacaoService).
     */
    public function precoVendaEfetivo(?string $precoVenda, ?float $descontoNfPercent = null): ?string
    {
        if ($precoVenda === null) {
            return null;
        }

        $desconto = $this->percentualDescontoCliente($descontoNfPercent);
        $efetivo = (float) $precoVenda * (1 - ($desconto / 100));

        return number_format($efetivo, 4, '.', '');
    }

    public function margemCalculada(?string $precoVenda, ?string $custoReferencia, ?float $descontoNfPercent = null): ?string
    {
        $precoEfetivo = $this->precoVendaEfetivo($precoVenda, $descontoNfPercent);
        if ($precoEfetivo === null || $custoReferencia === null) {
            return null;
        }

        return number_format((float) $precoEfetivo - (float) $custoReferencia, 4, '.', '');
    }

    public function margemPercentual(?string $precoVenda, ?string $custoReferencia, ?float $descontoNfPercent = null): ?string
    {
        $precoEfetivo = $this->precoVendaEfetivo($precoVenda, $descontoNfPercent);
        if ($precoEfetivo === null || $custoReferencia === null || (float) $precoEfetivo <= 0) {
            return null;
        }

        $margem = (float) $precoEfetivo - (float) $custoReferencia;

        return number_format(($margem / (float) $precoEfetivo) * 100, 2, '.', '');
    }

    public function precoIdealPorMargemAlvo(?string $custoReferencia, ?string $percentualMargemAlvo): ?string
    {
        if ($custoReferencia === null || $percentualMargemAlvo === null) {
            return null;
        }

        $alvo = (float) $percentualMargemAlvo;
        if ($alvo <= 0 || $alvo >= 100 || (float) $custoReferencia <= 0) {
            return null;
        }

        $preco = (float) $custoReferencia / (1 - ($alvo / 100));

        return number_format($preco, 4, '.', '');
    }

    /**
     * Rentabilidade de um item usando custo e preço gravados no pedido (snapshot).
     *
     * @return array{
     *     custo_referencia: string|null,
     *     margem_por_um: string|null,
     *     margem_percentual: string|null,
     *     margem_total_linha: string|null,
     * }
     */
    public function detalheRentabilidadeItem(PedidoItem $item, ?float $descontoNfPercent = null): array
    {
        $custo = $item->custo_referencia !== null ? (string) $item->custo_referencia : null;
        $precoBruto = $item->preco_venda !== null ? (string) $item->preco_venda : null;
        $margemPorUm = $this->margemCalculada($precoBruto, $custo, $descontoNfPercent);
        $margemPercentual = $this->margemPercentual($precoBruto, $custo, $descontoNfPercent);
        $margemTotalLinha = null;

        if ($margemPorUm !== null && (float) $item->quantidade > 0) {
            $margemTotalLinha = number_format(
                (float) $margemPorUm * (float) $item->quantidade,
                2,
                '.',
                '',
            );
        }

        return [
            'custo_referencia' => $custo,
            'margem_por_um' => $margemPorUm,
            'margem_percentual' => $margemPercentual,
            'margem_total_linha' => $margemTotalLinha,
        ];
    }

    /**
     * Rentabilidade agregada do pedido: média ponderada da margem % por faturamento da linha (ADR-0088).
     * Linhas sem custo de referência entram no faturamento total, mas não na média ponderada.
     *
     * @param  iterable<PedidoItem>  $itens
     * @return array{margem_total: string|null, margem_percentual: string|null, faturamento: string}
     */
    public function rentabilidadePedido(iterable $itens, ?float $descontoNfPercent = null): array
    {
        $margemTotal = 0.0;
        $faturamento = 0.0;
        $faturamentoParaRentabilidade = 0.0;
        $somaPonderadaMargem = 0.0;

        foreach ($itens as $item) {
            $qty = (float) $item->quantidade;
            if ($qty <= 0) {
                continue;
            }

            $precoBruto = $item->preco_venda !== null ? (float) $item->preco_venda : 0.0;
            if ($precoBruto <= 0) {
                continue;
            }

            $precoEfetivo = (float) ($this->precoVendaEfetivo((string) $item->preco_venda, $descontoNfPercent) ?? '0');
            if ($precoEfetivo <= 0) {
                continue;
            }

            $faturamentoLinha = $precoEfetivo * $qty;
            $faturamento += $faturamentoLinha;

            $margemPercentual = $this->margemPercentual(
                (string) $item->preco_venda,
                $item->custo_referencia !== null ? (string) $item->custo_referencia : null,
                $descontoNfPercent,
            );

            if ($margemPercentual === null) {
                continue;
            }

            $pct = (float) $margemPercentual;
            $faturamentoParaRentabilidade += $faturamentoLinha;
            $somaPonderadaMargem += $pct * $faturamentoLinha;
            $margemTotal += ($precoEfetivo - (float) $item->custo_referencia) * $qty;
        }

        if ($faturamento <= 0) {
            return [
                'margem_total' => null,
                'margem_percentual' => null,
                'faturamento' => '0.00',
            ];
        }

        $margemPercentualPedido = null;
        if ($faturamentoParaRentabilidade > 0) {
            $margemPercentualPedido = number_format(
                $somaPonderadaMargem / $faturamentoParaRentabilidade,
                2,
                '.',
                '',
            );
        }

        return [
            'margem_total' => $faturamentoParaRentabilidade > 0
                ? number_format($margemTotal, 2, '.', '')
                : null,
            'margem_percentual' => $margemPercentualPedido,
            'faturamento' => number_format($faturamento, 2, '.', ''),
        ];
    }

    public function kgPorUnidadeMedicao(Fruta $fruta): float
    {
        return (float) $fruta->kg_por_unidade_medicao;
    }
}
