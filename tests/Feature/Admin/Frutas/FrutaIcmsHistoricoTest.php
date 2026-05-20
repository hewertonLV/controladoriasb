<?php

namespace Tests\Feature\Admin\Frutas;

use App\Enums\FrutaUmIcms;
use App\Models\Estado;
use App\Models\Fornecedor;
use App\Models\Fruta;
use App\Models\FrutaIcmsHistorico;
use App\Models\UnidadeNegocio;
use App\Services\Frutas\FrutaIcmsCalculoService;
use App\Services\Frutas\FrutaIcmsSyncService;
use Database\Seeders\EstadoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FrutaIcmsHistoricoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoSeeder::class);
    }

    public function test_sync_registra_historico_quando_icms_altera(): void
    {
        $fruta = Fruta::factory()->comIcmsCeara([
            'entrada_nacional' => '20.00',
            'entrada_externo' => '0.00',
            'entrada_um' => FrutaUmIcms::KG->value,
        ])->create();

        $antes = FrutaIcmsHistorico::query()->where('fruta_id', $fruta->id)->count();

        app(FrutaIcmsSyncService::class)->syncEstado($fruta, Estado::ID_CEARA, [
            'entrada_nacional' => '50.00',
            'entrada_externo' => '0.00',
            'entrada_um_nacional' => FrutaUmIcms::KG->value,
            'saida_nacional' => '0.00',
        ]);

        $this->assertGreaterThan($antes, FrutaIcmsHistorico::query()->where('fruta_id', $fruta->id)->count());

        $vigente = FrutaIcmsHistorico::query()
            ->where('fruta_id', $fruta->id)
            ->where('id_estado', Estado::ID_CEARA)
            ->vigente()
            ->first();

        $this->assertNotNull($vigente);
        $this->assertSame('50.00', number_format((float) $vigente->entrada_nacional, 2, '.', ''));
    }

    public function test_calculo_usa_icms_vigente_na_data_referencia(): void
    {
        $fornecedor = Fornecedor::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);
        $unidade = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'id_estado' => Estado::ID_CEARA,
        ]);
        $fruta = Fruta::factory()->comIcmsCeara([
            'entrada_nacional' => '20.00',
            'entrada_externo' => '0.00',
            'entrada_um' => FrutaUmIcms::KG->value,
        ])->create(['kg_por_unidade_medicao' => 10]);

        $historicoAntigo = FrutaIcmsHistorico::query()
            ->where('fruta_id', $fruta->id)
            ->where('id_estado', Estado::ID_CEARA)
            ->vigente()
            ->firstOrFail();
        $historicoAntigo->update(['created_at' => Carbon::parse('2026-01-01 10:00:00')]);

        app(FrutaIcmsSyncService::class)->syncEstado($fruta, Estado::ID_CEARA, [
            'entrada_nacional' => '100.00',
            'entrada_externo' => '0.00',
            'entrada_um_nacional' => FrutaUmIcms::KG->value,
        ]);

        $calculo = app(FrutaIcmsCalculoService::class);

        $icmsNaDataAntiga = $calculo->calcularEntradaPorKg(
            $fruta,
            $unidade,
            $fornecedor,
            null,
            Carbon::parse('2026-02-01 12:00:00'),
        );

        $icmsAtual = $calculo->calcularEntradaPorKg($fruta, $unidade, $fornecedor);

        $this->assertSame('20.00', $icmsNaDataAntiga);
        $this->assertSame('100.00', $icmsAtual);
    }
}
