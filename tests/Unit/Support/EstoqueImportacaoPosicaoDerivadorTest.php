<?php

namespace Tests\Unit\Support;

use App\Support\Estoques\EstoqueImportacaoPosicaoDerivador;
use PHPUnit\Framework\TestCase;

class EstoqueImportacaoPosicaoDerivadorTest extends TestCase
{
    public function test_deriva_kg_e_preco_medio_a_partir_de_um_e_valor_total(): void
    {
        $posicao = EstoqueImportacaoPosicaoDerivador::derivar(10.0, 5.0, 250.0);

        $this->assertSame('5.00', $posicao['qtd_fruta_um']);
        $this->assertSame('250.00', $posicao['valor_total']);
        $this->assertSame('50.00', $posicao['qtd_fruta_kg']);
        $this->assertSame('5.00', $posicao['preco_medio_kg']);
        $this->assertSame('50.00', $posicao['preco_medio_um']);
    }

    public function test_valor_total_zero_com_quantidade_zero(): void
    {
        $posicao = EstoqueImportacaoPosicaoDerivador::derivar(10.0, 0.0, 0.0);

        $this->assertSame('0.00', $posicao['qtd_fruta_kg']);
        $this->assertSame('0.00', $posicao['preco_medio_kg']);
    }

    public function test_quantidade_negativa_deriva_kg_e_preco_negativos(): void
    {
        $posicao = EstoqueImportacaoPosicaoDerivador::derivar(10.0, -2.0, -100.0);

        $this->assertSame('-2.00', $posicao['qtd_fruta_um']);
        $this->assertSame('-100.00', $posicao['valor_total']);
        $this->assertSame('-20.00', $posicao['qtd_fruta_kg']);
        $this->assertSame('5.00', $posicao['preco_medio_kg']);
    }
}
