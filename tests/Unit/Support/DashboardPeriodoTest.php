<?php

namespace Tests\Unit\Support;

use App\Support\Dashboard\DashboardPeriodo;
use PHPUnit\Framework\TestCase;

class DashboardPeriodoTest extends TestCase
{
    public function test_mes_atual_termina_no_dia_de_hoje(): void
    {
        $periodo = DashboardPeriodo::resolver(now()->format('Y-m'));

        $this->assertSame(now()->startOfMonth()->toDateString(), $periodo->inicio->toDateString());
        $this->assertSame(now()->toDateString(), $periodo->fim->toDateString());
    }

    public function test_mes_passado_termina_no_ultimo_dia_do_mes(): void
    {
        $periodo = DashboardPeriodo::resolver('2020-01');

        $this->assertSame('2020-01-01', $periodo->inicio->toDateString());
        $this->assertSame('2020-01-31', $periodo->fim->toDateString());
        $this->assertSame('mes', $periodo->tipo);
        $this->assertNull($periodo->dia);
    }

    public function test_dia_especifico_usa_inicio_e_fim_no_mesmo_dia(): void
    {
        $periodo = DashboardPeriodo::resolver(null, '2024-06-15');

        $this->assertSame('2024-06-15', $periodo->inicio->toDateString());
        $this->assertSame('2024-06-15', $periodo->fim->toDateString());
        $this->assertSame('dia', $periodo->tipo);
        $this->assertSame('2024-06-15', $periodo->dia);
        $this->assertSame('15/06/2024', $periodo->label);
    }

    public function test_dia_tem_prioridade_sobre_mes(): void
    {
        $periodo = DashboardPeriodo::resolver('2020-01', '2024-06-15');

        $this->assertSame('dia', $periodo->tipo);
        $this->assertSame('2024-06-15', $periodo->dia);
    }
}
