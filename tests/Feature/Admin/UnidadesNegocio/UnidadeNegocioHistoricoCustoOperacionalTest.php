<?php

namespace Tests\Feature\Admin\UnidadesNegocio;

use App\Models\HistoricoCOUnNg;
use App\Models\UnidadeNegocio;

class UnidadeNegocioHistoricoCustoOperacionalTest extends UnidadeNegocioTestCase
{
    public function test_criacao_gera_historico_vigente(): void
    {
        $unidade = UnidadeNegocio::factory()->create([
            'custo_operacional' => '25.00',
        ]);

        $this->assertDatabaseHas('historico_c_o_un_ng', [
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => '25.00',
            'status_position' => true,
        ]);

        $this->assertSame(1, HistoricoCOUnNg::query()->where('id_unidade_negocio', $unidade->id)->count());
    }

    public function test_alteracao_custo_operacional_arquiva_anterior_e_cria_novo_vigente(): void
    {
        $unidade = UnidadeNegocio::factory()->create([
            'custo_operacional' => '10.00',
        ]);

        $historicoInicial = HistoricoCOUnNg::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->vigente()
            ->firstOrFail();

        $unidade->update(['custo_operacional' => '15.50']);

        $historicoInicial->refresh();
        $this->assertFalse($historicoInicial->status_position);

        $this->assertDatabaseHas('historico_c_o_un_ng', [
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => '15.50',
            'status_position' => true,
        ]);

        $this->assertSame(2, HistoricoCOUnNg::query()->where('id_unidade_negocio', $unidade->id)->count());
    }

    public function test_mesmo_custo_operacional_nao_duplica_historico(): void
    {
        $unidade = UnidadeNegocio::factory()->create([
            'custo_operacional' => '30.00',
        ]);

        $unidade->update(['nome' => 'OUTRO NOME']);

        $this->assertSame(1, HistoricoCOUnNg::query()->where('id_unidade_negocio', $unidade->id)->count());
    }

    public function test_atualizacao_com_mesmo_valor_decimal_nao_duplica(): void
    {
        $unidade = UnidadeNegocio::factory()->create([
            'custo_operacional' => '12.30',
        ]);

        $unidade->update(['custo_operacional' => '12.3']);

        $this->assertSame(1, HistoricoCOUnNg::query()->where('id_unidade_negocio', $unidade->id)->count());
    }
}
