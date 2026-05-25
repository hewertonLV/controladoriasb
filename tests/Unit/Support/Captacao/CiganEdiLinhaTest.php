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
}
