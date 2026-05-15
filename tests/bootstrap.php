<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

if (! function_exists('bccomp')) {
    /**
     * Polyfill mínima para ambientes sem ext-bcmath (ex.: CI local).
     * Usada apenas em testes; produção deve habilitar ext-bcmath.
     */
    function bccomp(string $left_operand, string $right_operand, ?int $scale = null): int
    {
        $scale ??= 0;
        $l = round((float) $left_operand, $scale);
        $r = round((float) $right_operand, $scale);

        return $l <=> $r;
    }
}
