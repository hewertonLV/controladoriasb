<?php

namespace Tests\Unit\Support\Captacao\Seed;

use App\Support\Captacao\Seed\CaptacaoExemploPlanilhaPreco;
use App\Support\Captacao\Seed\CaptacaoExemploPlanilhaReader;
use Tests\TestCase;

class CaptacaoExemploPlanilhaReaderTest extends TestCase
{
    public function test_preco_efetivo_usa_promocional_quando_maior_que_zero(): void
    {
        $this->assertSame(55.5, CaptacaoExemploPlanilhaPreco::precoEfetivo(55.5, 70.5));
    }

    public function test_preco_efetivo_cai_para_tabela_quando_promo_zero(): void
    {
        $this->assertSame(64.5, CaptacaoExemploPlanilhaPreco::precoEfetivo(0.0, 64.5));
        $this->assertSame(64.5, CaptacaoExemploPlanilhaPreco::precoEfetivo(null, 64.5));
    }

    public function test_parse_valor_brasileiro(): void
    {
        $this->assertSame(64.5, CaptacaoExemploPlanilhaPreco::parseValorBr('R$64,50'));
        $this->assertSame(120.6, CaptacaoExemploPlanilhaPreco::parseValorBr('R$120,60'));
        $this->assertSame(0.0, CaptacaoExemploPlanilhaPreco::parseValorBr('R$0,00'));
    }

    public function test_ler_planilha_exemplo_agrupa_pedidos_por_cliente(): void
    {
        $path = base_path('planilhas/captação exemplo.xlsx');
        if (! is_file($path)) {
            $this->markTestSkipped('Planilha planilhas/captação exemplo.xlsx não disponível.');
        }

        $pedidos = (new CaptacaoExemploPlanilhaReader)->lerArquivo($path);

        $this->assertGreaterThan(100, count($pedidos));

        $primeiro = $pedidos[0];
        $this->assertSame('941', $primeiro['codigo_cliente']);
        $this->assertSame('2150217-0', $primeiro['numero_pedido']);
        $this->assertNotEmpty($primeiro['itens']);
        $this->assertSame('1.000', $primeiro['itens'][0]['quantidade']);
        $this->assertSame('64.5000', $primeiro['itens'][0]['preco_venda']);
    }
}
