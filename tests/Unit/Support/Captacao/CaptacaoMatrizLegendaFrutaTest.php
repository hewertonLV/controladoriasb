<?php

namespace Tests\Unit\Support\Captacao;

use App\Support\Captacao\CaptacaoMatrizLegendaFruta;
use PHPUnit\Framework\TestCase;

class CaptacaoMatrizLegendaFrutaTest extends TestCase
{
    public function test_duas_linhas_por_palavras_sem_perder_texto(): void
    {
        [$l1, $l2] = CaptacaoMatrizLegendaFruta::duasLinhas('BANANA NANICA');

        $this->assertSame('BANANA', $l1);
        $this->assertSame('NANICA', $l2);
        $this->assertSame('BANANA NANICA', trim($l1.' '.$l2));
    }

    public function test_duas_linhas_palavra_unica_longa_divide_no_meio(): void
    {
        [$l1, $l2] = CaptacaoMatrizLegendaFruta::duasLinhas('MORANGUEIRO');

        $this->assertSame('MORANG', $l1);
        $this->assertSame('UEIRO', $l2);
        $this->assertSame('MORANGUEIRO', $l1.$l2);
    }

    public function test_nome_curto_fica_em_uma_linha(): void
    {
        [$l1, $l2] = CaptacaoMatrizLegendaFruta::duasLinhas('MAÇÃ');

        $this->assertSame('MAÇÃ', $l1);
        $this->assertSame('', $l2);
    }
}
