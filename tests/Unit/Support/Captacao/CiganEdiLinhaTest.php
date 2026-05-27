<?php

namespace Tests\Unit\Support\Captacao;

use App\Support\Captacao\Cigan\CiganEdiLinha;
use PHPUnit\Framework\TestCase;

class CiganEdiLinhaTest extends TestCase
{
    public function test_monta_linha_com_largura_fixa(): void
    {
        $linha = (new CiganEdiLinha(20))
            ->colocar(1, 1, 'N')
            ->colocar(3, 7, '1')
            ->colocar(9, 18, '42', true)
            ->linha();

        $this->assertSame(20, strlen($linha));
        $this->assertSame('N', substr($linha, 0, 1));
        $this->assertSame('1    ', substr($linha, 2, 5));
        $this->assertSame('0000000042', substr($linha, 8, 10));
    }

    public function test_colocar_exato_preserva_espacos_a_esquerda(): void
    {
        $linha = (new CiganEdiLinha(10))
            ->colocarExato(3, 7, '  001')
            ->linha();

        $this->assertSame('  001', substr($linha, 2, 5));
    }

    public function test_texto_com_acento_nao_desloca_campos_posteriores(): void
    {
        $linha = (new CiganEdiLinha(719))
            ->colocar(1, 1, 'I')
            ->colocar(115, 314, 'MAÇA PACOTE 1 KG')
            ->colocar(656, 658, '125', true)
            ->colocarExato(679, 679, 'S')
            ->linha();

        $this->assertSame(719, strlen($linha));
        $this->assertSame('125', substr($linha, 655, 3));
        $this->assertSame('S', substr($linha, 678, 1));
        $this->assertStringContainsString('MA', substr($linha, 114, 20));
    }
}
