<?php

namespace Tests\Unit\Captacao;

use App\Models\Captacao\PedidoItem;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Services\Captacao\CaptacaoPrecificacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaptacaoPrecificacaoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_custo_referencia_usa_preco_medio_um_do_estoque(): void
    {
        $galpao = UnidadeNegocio::factory()->galpaoOperacional()->create();
        $fruta = Fruta::factory()->create([
            'unidade_medicao' => 'CX',
            'kg_por_unidade_medicao' => '12.00',
        ]);

        Estoque::factory()->create([
            'id_unidade_negocio' => $galpao->id,
            'id_fruta' => $fruta->id,
            'preco_medio_kg' => '3.0000',
            'preco_medio_um' => '36.0000',
            'qtd_fruta_kg' => '120',
            'qtd_fruta_um' => '10',
            'ativo_unico' => 1,
        ]);

        $custo = app(CaptacaoPrecificacaoService::class)->custoReferenciaPorUm($galpao->id, $fruta);

        $this->assertSame('36.0000', $custo);
    }

    public function test_custo_referencia_deriva_da_um_quando_preco_medio_um_zerado(): void
    {
        $galpao = UnidadeNegocio::factory()->galpaoOperacional()->create();
        $fruta = Fruta::factory()->create([
            'unidade_medicao' => 'PCT',
            'kg_por_unidade_medicao' => '2.50',
        ]);

        Estoque::factory()->create([
            'id_unidade_negocio' => $galpao->id,
            'id_fruta' => $fruta->id,
            'preco_medio_kg' => '4.0000',
            'preco_medio_um' => '0.00',
            'qtd_fruta_kg' => '40',
            'qtd_fruta_um' => '16',
            'ativo_unico' => 1,
        ]);

        $custo = app(CaptacaoPrecificacaoService::class)->custoReferenciaPorUm($galpao->id, $fruta);

        $this->assertSame('10.0000', $custo);
    }

    public function test_detalhe_rentabilidade_item_usa_snapshot_do_pedido(): void
    {
        $item = new PedidoItem([
            'quantidade' => '10',
            'preco_venda' => '50.0000',
            'custo_referencia' => '35.0000',
        ]);

        $detalhe = app(CaptacaoPrecificacaoService::class)->detalheRentabilidadeItem($item);

        $this->assertSame('35.0000', $detalhe['custo_referencia']);
        $this->assertSame('15.0000', $detalhe['margem_por_um']);
        $this->assertSame('30.00', $detalhe['margem_percentual']);
        $this->assertSame('150.00', $detalhe['margem_total_linha']);
    }

    public function test_custo_referencia_null_sem_saldo(): void
    {
        $galpao = UnidadeNegocio::factory()->galpaoOperacional()->create();
        $fruta = Fruta::factory()->create();

        Estoque::factory()->create([
            'id_unidade_negocio' => $galpao->id,
            'id_fruta' => $fruta->id,
            'preco_medio_kg' => '5.0000',
            'preco_medio_um' => '25.0000',
            'qtd_fruta_kg' => '0',
            'qtd_fruta_um' => '0',
            'ativo_unico' => 1,
        ]);

        $custo = app(CaptacaoPrecificacaoService::class)->custoReferenciaPorUm($galpao->id, $fruta);

        $this->assertNull($custo);
    }

    public function test_rentabilidade_pedido_media_ponderada_por_faturamento(): void
    {
        $service = app(CaptacaoPrecificacaoService::class);

        $itens = collect([
            new PedidoItem([
                'quantidade' => '10',
                'preco_venda' => '12.50',
                'custo_referencia' => '8.0000',
            ]),
            new PedidoItem([
                'quantidade' => '5',
                'preco_venda' => '20.00',
                'custo_referencia' => '16.0000',
            ]),
        ]);

        $rent = $service->rentabilidadePedido($itens);

        $this->assertSame('225.00', $rent['faturamento']);
        $this->assertSame('65.00', $rent['margem_total']);
        $this->assertSame('28.89', $rent['margem_percentual']);
    }

    public function test_preco_venda_efetivo_aplica_desconto_nf(): void
    {
        $service = app(CaptacaoPrecificacaoService::class);

        $this->assertSame('11.2500', $service->precoVendaEfetivo('12.50', 10.0));
        $this->assertSame('12.5000', $service->precoVendaEfetivo('12.50', 0.0));
        $this->assertSame('12.5000', $service->precoVendaEfetivo('12.50', null));
    }

    public function test_detalhe_rentabilidade_item_aplica_desconto_nf(): void
    {
        $item = new PedidoItem([
            'quantidade' => '7',
            'preco_venda' => '12.50',
            'custo_referencia' => '8.0000',
        ]);

        $detalhe = app(CaptacaoPrecificacaoService::class)->detalheRentabilidadeItem($item, 10.0);

        $this->assertSame('3.2500', $detalhe['margem_por_um']);
        $this->assertSame('28.89', $detalhe['margem_percentual']);
        $this->assertSame('22.75', $detalhe['margem_total_linha']);
    }

    public function test_rentabilidade_pedido_aplica_desconto_nf_no_faturamento(): void
    {
        $service = app(CaptacaoPrecificacaoService::class);

        $itens = collect([
            new PedidoItem([
                'quantidade' => '10',
                'preco_venda' => '12.50',
                'custo_referencia' => '8.0000',
            ]),
            new PedidoItem([
                'quantidade' => '5',
                'preco_venda' => '20.00',
                'custo_referencia' => '16.0000',
            ]),
        ]);

        $rent = $service->rentabilidadePedido($itens, 10.0);

        $this->assertSame('202.50', $rent['faturamento']);
        $this->assertSame('42.50', $rent['margem_total']);
        $this->assertSame('20.99', $rent['margem_percentual']);
    }

    public function test_rentabilidade_pedido_ignora_linha_sem_custo_na_media(): void
    {
        $service = app(CaptacaoPrecificacaoService::class);

        $itens = collect([
            new PedidoItem([
                'quantidade' => '10',
                'preco_venda' => '12.50',
                'custo_referencia' => '8.0000',
            ]),
            new PedidoItem([
                'quantidade' => '100',
                'preco_venda' => '10.00',
                'custo_referencia' => null,
            ]),
        ]);

        $rent = $service->rentabilidadePedido($itens);

        $this->assertSame('1125.00', $rent['faturamento']);
        $this->assertSame('45.00', $rent['margem_total']);
        $this->assertSame('36.00', $rent['margem_percentual']);
    }
}
