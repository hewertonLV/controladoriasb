<?php

namespace Tests\Unit\Support;

use App\Models\HistoricoCOUnNg;
use App\Models\UnidadeNegocio;
use App\Support\Movimentacoes\CustoOperacionalSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CustoOperacionalSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_vigente_na_data_retorna_ultimo_historico_anterior_a_referencia(): void
    {
        $unidade = UnidadeNegocio::factory()->create(['custo_operacional' => 1]);

        Carbon::setTestNow('2026-01-01 10:00:00');
        $antigo = HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => 2,
            'status_position' => false,
        ]);
        HistoricoCOUnNg::query()->whereKey($antigo->id)->update(['created_at' => '2026-01-01 10:00:00']);

        Carbon::setTestNow('2026-06-01 10:00:00');
        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => 9,
            'status_position' => true,
        ]);

        $snapshot = CustoOperacionalSnapshot::vigenteNaData(
            $unidade->id,
            Carbon::parse('2026-03-15 12:00:00'),
        );

        $this->assertSame(2.0, $snapshot['valor']);
        $this->assertSame((int) $antigo->id, $snapshot['id']);

        Carbon::setTestNow();
    }
}
