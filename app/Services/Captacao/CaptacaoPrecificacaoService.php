<?php

namespace App\Services\Captacao;

use App\Models\Captacao\PedidoItem;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Support\Movimentacoes\CustoOperacionalSnapshot;
use App\Support\Movimentacoes\VendaCustoOperacionalHub;
use Illuminate\Support\Carbon;

final class CaptacaoPrecificacaoService
{
    /**
     * Custo de referência na UM da fruta (ADR-0073): PM do estoque na unidade de saída física.
     * Se a saída for HUB, soma o CO (R$/kg) da unidade de faturamento convertido para a UM (ADR-0077 / venda HUB).
     */
    /**
     * @return array{
     *     pm_um: string|null,
     *     co_um: string|null,
     *     co_kg: string|null,
     *     custo_final: string|null,
     *     eh_saida_hub: bool,
     * }
     */
    public function detalheCustoSaidaFisica(
        int $idUnidadeEstoqueSaida,
        int $idUnidadeFaturamento,
        Fruta $fruta,
        ?Carbon $dataReferencia = null,
    ): array {
        $pmUm = $this->custoReferenciaPorUm($idUnidadeEstoqueSaida, $fruta);
        $unidadeSaida = UnidadeNegocio::query()->find($idUnidadeEstoqueSaida);
        $ehHub = $unidadeSaida !== null && VendaCustoOperacionalHub::saidaFisicaEhHubUnidade($unidadeSaida);

        if ($pmUm === null) {
            return [
                'pm_um' => null,
                'co_um' => null,
                'co_kg' => null,
                'custo_final' => null,
                'eh_saida_hub' => $ehHub,
            ];
        }

        $coKg = null;
        $coUm = null;

        if ($ehHub) {
            $co = CustoOperacionalSnapshot::vigenteNaData(
                $idUnidadeFaturamento,
                $dataReferencia ?? now(),
            );
            $coKg = number_format($co['valor'], 4, '.', '');
            $kgPorUm = $this->kgPorUnidadeMedicao($fruta);
            if ($kgPorUm > 0) {
                $coUm = number_format($co['valor'] * $kgPorUm, 4, '.', '');
            }
        }

        $custoFinal = $pmUm;
        if ($coUm !== null && (float) $coUm > 0) {
            $custoFinal = number_format((float) $pmUm + (float) $coUm, 4, '.', '');
        }

        return [
            'pm_um' => $pmUm,
            'co_um' => $coUm,
            'co_kg' => $coKg,
            'custo_final' => $custoFinal,
            'eh_saida_hub' => $ehHub,
        ];
    }

    /**
     * @param  array{
     *     pm_um: string|null,
     *     co_um: string|null,
     *     co_kg: string|null,
     *     custo_final: string|null,
     *     eh_saida_hub: bool,
     * }  $detalhe
     * @return array{pm: string|null, co: string|null, final: string|null}
     */
    public function detalheCustoSaidaFisicaParaApi(array $detalhe): array
    {
        return [
            'pm' => $detalhe['pm_um'] !== null
                ? number_format((float) $detalhe['pm_um'], 2, '.', '')
                : null,
            'co' => $detalhe['eh_saida_hub'] && $detalhe['co_um'] !== null
                ? number_format((float) $detalhe['co_um'], 2, '.', '')
                : null,
            'final' => $detalhe['custo_final'] !== null
                ? number_format((float) $detalhe['custo_final'], 2, '.', '')
                : null,
        ];
    }

    public function custoReferenciaPorUmNaSaidaFisica(
        int $idUnidadeEstoqueSaida,
        int $idUnidadeFaturamento,
        Fruta $fruta,
        ?Carbon $dataReferencia = null,
    ): ?string {
        return $this->detalheCustoSaidaFisica(
            $idUnidadeEstoqueSaida,
            $idUnidadeFaturamento,
            $fruta,
            $dataReferencia,
        )['custo_final'];
    }

    /**
     * Custo de referência na UM da fruta (ADR-0073): PM por unidade de medição do estoque da unidade informada.
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
