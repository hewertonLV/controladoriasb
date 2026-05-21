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
    }
}
