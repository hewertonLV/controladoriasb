<?php

namespace App\Support\Captacao;

final class RomaneioRotaPdfNomeArquivo
{
    public static function gerar(string $rotaNome, ?string $motoristaNome): string
    {
        $motorista = trim((string) ($motoristaNome ?? ''));
        if ($motorista === '') {
            $motorista = 'Sem motorista';
        }

        $base = trim($rotaNome).' - '.$motorista;
        $safe = preg_replace('/[\\\\\/:*?"<>|]/u', '-', $base) ?? $base;
        $safe = preg_replace('/\s+/u', ' ', trim($safe)) ?? trim($safe);

        return $safe.'.pdf';
    }
}
