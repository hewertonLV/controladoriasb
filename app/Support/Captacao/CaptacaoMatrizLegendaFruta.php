<?php

namespace App\Support\Captacao;

final class CaptacaoMatrizLegendaFruta
{
    /**
     * Divide o nome em até duas linhas (palavras ou meio do texto), sem omitir caracteres.
     *
     * @return array{0: string, 1: string}
     */
    public static function duasLinhas(string $nome): array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return ['', ''];
        }

        $partes = preg_split('/\s+/u', $nome, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($partes) >= 2) {
            $meio = (int) ceil(count($partes) / 2);

            return [
                implode(' ', array_slice($partes, 0, $meio)),
                implode(' ', array_slice($partes, $meio)),
            ];
        }

        $len = mb_strlen($nome);
        if ($len <= 10) {
            return [$nome, ''];
        }

        $meio = (int) ceil($len / 2);

        return [
            mb_substr($nome, 0, $meio),
            mb_substr($nome, $meio),
        ];
    }
}
