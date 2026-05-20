<?php

namespace Tests\Feature\Admin\Frutas;

use App\Enums\FrutaUmIcms;
use App\Models\Cliente;
use App\Models\Estado;
use App\Models\Fornecedor;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Services\Frutas\FrutaIcmsCalculoService;
use App\Services\Frutas\FrutaIcmsSyncService;
use Database\Seeders\EstadoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrutaIcmsCalculoPeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoSeeder::class);
    }

    public function test_entrada_ce_usa_valor_fixo_por_kg(): void
    {
        $fornecedor = Fornecedor::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);
        $unidade = UnidadeNegocio::factory()->create(['id_estado' => Estado::ID_CEARA]);
        $fruta = Fruta::factory()->comIcmsCeara([
            'entrada_nacional' => '0.00',
            'entrada_externo' => '0.26',
            'entrada_um_externo' => FrutaUmIcms::KG->value,
        ])->create(['kg_por_unidade_medicao' => 10]);

        $icmsKg = app(FrutaIcmsCalculoService::class)->calcularEntradaPorKg($fruta, $unidade, $fornecedor);

        $this->assertSame('0.26', $icmsKg);
    }

    public function test_saida_pe_percentual_dentro_e_fora_do_estado(): void
    {
        $unidadePe = UnidadeNegocio::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);
        $unidadeCe = UnidadeNegocio::factory()->create(['id_estado' => Estado::ID_CEARA]);
        $clienteDentro = Cliente::factory()->create(['id_unidade_negocio' => $unidadePe->id]);
        $clienteFora = Cliente::factory()->create(['id_unidade_negocio' => $unidadeCe->id]);

        $fruta = Fruta::factory()->comIcmsPernambuco()->create();

        $calculo = app(FrutaIcmsCalculoService::class);

        $dentro = $calculo->calcularSaidaSobreValorVenda(
            $fruta,
            $unidadePe,
            $clienteDentro,
            '1000.00',
            100,
            10,
        );

        $fora = $calculo->calcularSaidaSobreValorVenda(
            $fruta,
            $unidadePe,
            $clienteFora,
            '1000.00',
            100,
            10,
        );

        $this->assertSame('205.00', $dentro['valor_icms_total']);
        $this->assertSame('120.00', $fora['valor_icms_total']);
        $this->assertSame('2.05', $dentro['valor_icms_kg']);
        $this->assertSame('12.00', $fora['valor_icms_um']);
    }

    public function test_sync_pe_forca_um_pct_quando_ha_aliquota(): void
    {
        $fruta = Fruta::factory()->create();

        app(FrutaIcmsSyncService::class)->syncEstado($fruta, Estado::ID_PERNAMBUCO, [
            'saida_nacional' => '18.00',
            'saida_um_nacional' => FrutaUmIcms::KG->value,
            'saida_importada' => '10.00',
            'saida_um_importada' => FrutaUmIcms::KG->value,
        ]);

        $saida = $fruta->icms()->where('id_estado', Estado::ID_PERNAMBUCO)->where('operacao', 'SAIDA')->firstOrFail();

        $this->assertSame(FrutaUmIcms::PCT->value, $saida->um_icms_venda_nacional);
        $this->assertSame(FrutaUmIcms::PCT->value, $saida->um_icms_venda_importada);
    }
}
