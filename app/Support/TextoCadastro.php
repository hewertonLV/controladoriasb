<?php

namespace App\Support;

/**
 * Normalizações reutilizáveis em cadastros (documentos, códigos CIGAM, texto).
 */
final class TextoCadastro
{
    public static function somenteDigitos(?string $value): string
    {
        return preg_replace('/\D/', '', (string) $value) ?? '';
    }

    /**
     * Normaliza ID SBS removendo tudo que não for dígito.
     * A validação de positivo/inexistente é feita via FormRequest.
     */
    public static function normalizarIdSbsInteiroPositivo(?string $value): string
    {
        $digits = self::somenteDigitos((string) $value);

        return $digits;
    }

    /**
     * Normaliza status para sempre ficar em MAIÚSCULO e sem espaços.
     * A validação de valores permitidos é feita via FormRequest.
     */
    public static function normalizarStatusAtivoInativo(?string $value): string
    {
        return mb_strtoupper(trim((string) $value), 'UTF-8');
    }

    /**
     * Remove não numéricos; se vazio retorna string vazia; se mais de 6 dígitos
     * retorna os dígitos sem pad (para falhar validação); caso contrário pad à esquerda até 6.
     */
    public static function normalizarIdCigamAteSeisDigitos(?string $value): string
    {
        $digits = self::somenteDigitos((string) $value);

        if ($digits === '') {
            return '';
        }

        if (strlen($digits) > 6) {
            return $digits;
        }

        return str_pad($digits, 6, '0', STR_PAD_LEFT);
    }

    public static function normalizarMaiusculas(?string $value): string
    {
        return mb_strtoupper(trim((string) $value), 'UTF-8');
    }

    public static function normalizarMaiusculasOuNulo(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        return mb_strtoupper($trimmed, 'UTF-8');
    }

    /**
     * Normaliza valor decimal não negativo com casas fixas (ponto como separador).
     */
    public static function normalizarDecimalNaoNegativo(mixed $value, int $decimals = 2): string
    {
        $normalized = str_replace(',', '.', trim((string) $value));

        if ($normalized === '') {
            return number_format(0, $decimals, '.', '');
        }

        $float = max(0, (float) $normalized);

        return number_format($float, $decimals, '.', '');
    }

    /**
     * Interpreta valores monetários em formatos comuns (BR/US) após remover símbolos/texto.
     *
     * Exemplos aceitos após limpeza interna: "R$ 1.200,55", "1.200,55", "1200,55",
     * "1,200.55", "1200.55".
     *
     * @return string Decimal fixo com ponto (sempre >= 0).
     */
    public static function normalizarValorMonetarioBrasileiro(mixed $value, int $decimals = 2): string
    {
        $raw = trim((string) $value);
        $raw = str_replace(["\xC2\xA0", ' '], '', $raw);
        $raw = preg_replace('/\s+/u', '', $raw) ?? '';
        $raw = str_ireplace(['r$', 'rs$'], '', $raw);
        $raw = str_replace('$', '', $raw);
        $raw = preg_replace('/[^\d,.]/u', '', $raw) ?? '';

        if ($raw === '') {
            return number_format(0, $decimals, '.', '');
        }

        $hasComma = str_contains($raw, ',');
        $hasDot = str_contains($raw, '.');

        if ($hasComma && $hasDot) {
            if (strrpos($raw, ',') > strrpos($raw, '.')) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($hasComma) {
            $raw = str_replace(',', '.', $raw);
        } elseif ($hasDot) {
            $parts = explode('.', $raw);
            if (count($parts) > 2) {
                $frac = array_pop($parts);

                $raw = implode('', $parts);
                if ($frac !== '') {
                    $raw .= '.'.$frac;
                }
            } elseif (count($parts) === 2 && isset($parts[0], $parts[1]) && strlen($parts[1]) === 3 && strlen($parts[0]) <= 3) {
                $raw = $parts[0].$parts[1];
            }
        }

        $float = (float) $raw;
        if ($float < 0) {
            $float = 0.0;
        }

        return number_format($float, $decimals, '.', '');
    }
}
