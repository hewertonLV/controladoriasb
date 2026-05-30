<?php

namespace App\Support\Captacao\Seed;

final class CaptacaoExemploPlanilhaPreco
{
    public static function parseValorBr(mixed $valor): ?float
    {
        if ($valor === null) {
            return null;
        }

        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        $texto = str_replace(["\xc2\xa0", ' '], '', $texto);
        $texto = str_ireplace('R$', '', $texto);

        if ($texto === '') {
            return null;
        }

        if (str_contains($texto, ',')) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        }

        if (! is_numeric($texto)) {
            return null;
        }

        return (float) $texto;
    }

    public static function precoEfetivo(?float $precoPromocional, ?float $precoTabela): ?float
    {
        if ($precoPromocional !== null && $precoPromocional > 0) {
            return $precoPromocional;
        }

        if ($precoTabela !== null && $precoTabela > 0) {
            return $precoTabela;
        }

        return null;
    }
}
