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
        $this->attributes['saldo_estoque_fruta_kg'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
    }

    protected function setSaldoEstoqueFrutaUmAttribute(mixed $value): void
    {
        $this->attributes['saldo_estoque_fruta_um'] = TextoCadastro::normalizarValorMonetarioBrasileiro($value);
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
}
