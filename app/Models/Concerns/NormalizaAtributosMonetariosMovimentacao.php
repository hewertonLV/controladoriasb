<?php

namespace App\Models\Concerns;

use App\Models\Movimentacao;
use App\Support\TextoCadastro;

/**
 * Normalização monetária centralizada para {@see Movimentacao}.
 */
trait NormalizaAtributosMonetariosMovimentacao
{
    protected function setValorNfTotalAttribute(mixed $value): void
    {
        $this->attributes['valor_nf_total'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setValorNfUmAttribute(mixed $value): void
    {
        $this->attributes['valor_nf_um'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setValorNfKgAttribute(mixed $value): void
    {
        $this->attributes['valor_nf_kg'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setValorTotalMovimentacaoAttribute(mixed $value): void
    {
        $this->attributes['valor_total_movimentacao'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setValorCustoSaidaAttribute(mixed $value): void
    {
        $this->attributes['valor_custo_saida'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setResultadoMovimentacaoAttribute(mixed $value): void
    {
        $this->attributes['resultado_movimentacao'] = $this->normalizarDecimalComSinal($value);
    }

    protected function setValorDevolucaoTotalAttribute(mixed $value): void
    {
        $this->attributes['valor_devolucao_total'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setValorDevolucaoUmAttribute(mixed $value): void
    {
        $this->attributes['valor_devolucao_um'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setValorDevolucaoKgAttribute(mixed $value): void
    {
        $this->attributes['valor_devolucao_kg'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setValorCustoDevolucaoAttribute(mixed $value): void
    {
        $this->attributes['valor_custo_devolucao'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setResultadoDevolucaoAttribute(mixed $value): void
    {
        $this->attributes['resultado_devolucao'] = $this->normalizarDecimalComSinal($value);
    }

    protected function setValorIcmsTotalAttribute(mixed $value): void
    {
        $this->attributes['valor_icms_total'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setValorIcmsKgAttribute(mixed $value): void
    {
        $this->attributes['valor_icms_kg'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setValorIcmsUmAttribute(mixed $value): void
    {
        $this->attributes['valor_icms_um'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setValorFreteRateioAttribute(mixed $value): void
    {
        $this->attributes['valor_frete_rateio'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setValorFreteUmAttribute(mixed $value): void
    {
        $this->attributes['valor_frete_um'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setValorFreteKgAttribute(mixed $value): void
    {
        $this->attributes['valor_frete_kg'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setValorCustoOperacionalAttribute(mixed $value): void
    {
        $this->attributes['valor_custo_operacional'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setSaldoEstoqueFrutaKgAttribute(mixed $value): void
    {
        $this->attributes['saldo_estoque_fruta_kg'] = $this->normalizarDecimalComSinal($value);
    }

    protected function setSaldoEstoqueFrutaUmAttribute(mixed $value): void
    {
        $this->attributes['saldo_estoque_fruta_um'] = $this->normalizarDecimalComSinal($value);
    }

    protected function setPrecoMedioFrutaKgAttribute(mixed $value): void
    {
        $this->attributes['preco_medio_fruta_kg'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setPrecoMedioFrutaUmAttribute(mixed $value): void
    {
        $this->attributes['preco_medio_fruta_um'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setIcmsConvertidoKgAttribute(mixed $value): void
    {
        $this->attributes['icms_convertido_kg'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setQtdFrutaKgAttribute(mixed $value): void
    {
        $this->attributes['qtd_fruta_kg'] = TextoCadastro::normalizarDecimalNaoNegativo($value);
    }

    private function normalizarDecimalComSinal(mixed $value): string
    {
        if (is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        $raw = trim((string) ($value ?? '0'));
        $negative = str_starts_with($raw, '-');
        $normalized = TextoCadastro::normalizarValorMonetarioBrasileiro(ltrim($raw, '-'));

        return $negative && (float) $normalized > 0
            ? '-'.$normalized
            : $normalized;
    }
}
