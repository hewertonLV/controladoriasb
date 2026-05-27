<?php

namespace App\Support\Captacao\Cigan;

/**
 * Conversão UTF-8 → ISO-8859-1 para arquivos EDI Cigam (ANSI).
 */
final class CiganEdiEncoding
{
    public static function paraIso88591(string $conteudoUtf8): string
    {
        $utf8 = self::sanitizarUtf8($conteudoUtf8);

        if ($utf8 === '') {
            return '';
        }

        $convertido = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $utf8);
        if ($convertido !== false) {
            return $convertido;
        }

        $fallback = @mb_convert_encoding($utf8, 'ISO-8859-1', 'UTF-8');

        return $fallback !== false ? $fallback : '';
    }

    public static function textoLatin1(string $valor, int $tamanho): string
    {
        $valor = preg_replace('/\s+/', ' ', trim($valor)) ?? '';
        $valor = str_replace(["\r", "\n", "\t"], ' ', $valor);
        $valor = mb_strtoupper($valor, 'UTF-8');
        $latin1 = self::paraIso88591($valor);

        if (strlen($latin1) > $tamanho) {
            $latin1 = substr($latin1, 0, $tamanho);
        }

        return str_pad($latin1, $tamanho, ' ', STR_PAD_RIGHT);
    }

    private static function sanitizarUtf8(string $texto): string
    {
        if ($texto === '') {
            return '';
        }

        $limpo = @iconv('UTF-8', 'UTF-8//IGNORE', $texto);
        if ($limpo !== false) {
            return $limpo;
        }

        return mb_convert_encoding($texto, 'UTF-8', 'UTF-8');
    }
}
