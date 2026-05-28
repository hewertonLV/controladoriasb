<?php

namespace Tests\Unit\Support;

use App\Models\HistoricoCOUnNg;
use App\Models\UnidadeNegocio;
use App\Support\Estoques\EstoqueImportacaoCustoOperacional;
use Database\Seeders\EstadoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstoqueImportacaoCustoOperacionalTest extends TestCase
{
    use RefreshDatabase;

    public function test_soma_custo_operacional_ao_preco_medio_kg(): void
    {
        $this->seed(EstadoSeeder::class);

        $unidade = UnidadeNegocio::factory()->create(['possui_estoque' => true]);
        HistoricoCOUnNg::query()->where('id_unidade_negocio', $unidade->id)->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => '1.50',
            'status_position' => true,
        ]);

        $unidade->load('historicoCustoOperacionalAtual');
        $preco = EstoqueImportacaoCustoOperacional::precoMedioKgAplicandoCo('5.00', $unidade, true);

        $this->assertSame('6.50', $preco);
    }

    public function test_nao_soma_custo_operacional_quando_quantidade_importada_e_zero(): void
    {
        $this->seed(EstadoSeeder::class);

        $unidade = UnidadeNegocio::factory()->create(['possui_estoque' => true]);
        HistoricoCOUnNg::query()->where('id_unidade_negocio', $unidade->id)->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => '1.50',
            'status_position' => true,
        ]);

        $unidade->load('historicoCustoOperacionalAtual');
        $preco = EstoqueImportacaoCustoOperacional::precoMedioKgAplicandoCo(
            '0.00',
            $unidade,
            true,
            '0.00',
            '0.00',
        );

        $this->assertSame('0.00', $preco);
    }

    public function test_nao_soma_quando_switch_desligado(): void
    {
        $this->seed(EstadoSeeder::class);

        $unidade = UnidadeNegocio::factory()->create(['possui_estoque' => true]);
        HistoricoCOUnNg::query()->where('id_unidade_negocio', $unidade->id)->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => '1.50',
            'status_position' => true,
        ]);

        $unidade->load('historicoCustoOperacionalAtual');
        $preco = EstoqueImportacaoCustoOperacional::precoMedioKgAplicandoCo('5.00', $unidade, false);

        $this->assertSame('5.00', $preco);
    }

    public function test_usa_custo_do_cadastro_quando_nao_ha_historico_vigente(): void
    {
        $this->seed(EstadoSeeder::class);

        $unidade = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'custo_operacional' => '3.75',
        ]);
        HistoricoCOUnNg::query()->where('id_unidade_negocio', $unidade->id)->delete();

        $this->assertSame(3.75, EstoqueImportacaoCustoOperacional::resolverCustoOperacionalKg($unidade->fresh()));
    }

    public function test_enriquece_preview_sem_custo_operacional_kg(): void
    {
        $this->seed(EstadoSeeder::class);

        $unidade = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'custo_operacional' => '2.40',
        ]);
        HistoricoCOUnNg::query()->where('id_unidade_negocio', $unidade->id)->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => '2.40',
            'status_position' => true,
        ]);

        $resultado = EstoqueImportacaoCustoOperacional::enriquecerCoNoPreviewResultado([
            'atualizacoes' => [[
                'row_id' => 1,
                'id_unidade_negocio' => $unidade->id,
                'dados_novos' => [
                    'qtd_fruta_um' => '10.00',
                    'preco_medio_kg' => '5.00',
                ],
            ]],
        ]);

        $this->assertSame('2.40', $resultado['atualizacoes'][0]['dados_novos']['custo_operacional_kg']);
    }
}
