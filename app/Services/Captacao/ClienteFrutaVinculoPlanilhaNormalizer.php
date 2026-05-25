<?php

namespace App\Services\Captacao;

use App\Support\TextoCadastro;

final class ClienteFrutaVinculoPlanilhaNormalizer
{
    /**
     * @param  list<mixed>  $cells  [coluna A, coluna B]
     * @return array{dados: array<string, string>, erros: list<string>}
     */
    public function normalize(array $cells): array
    {
        $loja = trim((string) ($cells[0] ?? ''));
        $fruta = trim((string) ($cells[1] ?? ''));

        $erros = [];

        if ($loja === '') {
            $erros[] = 'Coluna A (loja) é obrigatória.';
        }

        if ($fruta === '') {
            $erros[] = 'Coluna B (fruta) é obrigatória.';
        }

        return [
            'dados' => [
                'loja' => $loja,
                'fruta' => $fruta,
                'loja_chave' => self::chaveNome($loja),
                'fruta_chave' => self::chaveNome($fruta),
            ],
            'erros' => $erros,
        ];
    }

    public static function chaveNome(?string $value): string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return '';
        }

        return mb_strtoupper(TextoCadastro::removerAcentos($trimmed), 'UTF-8');
    }
}
