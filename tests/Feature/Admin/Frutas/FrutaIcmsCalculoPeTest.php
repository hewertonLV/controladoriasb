<?php

namespace Tests\Feature\Admin\Frutas;

use App\Enums\FrutaIcmsEscopoVenda;
use App\Enums\FrutaIcmsOperacao;
use App\Enums\FrutaIcmsTipoValor;
use App\Enums\FrutaProcedencia;
use App\Models\Cliente;
use App\Models\Estado;
use App\Models\Fornecedor;
use App\Models\Fruta;
use App\Models\FrutaIcmsAliquota;
use App\Models\UnidadeNegocio;
use App\Services\Frutas\FrutaIcmsCalculoService;
use App\Services\Frutas\FrutaIcmsSyncService;
use App\Support\Frutas\FrutaIcmsLinhaFormulario;
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

    public function test_entrada_ce_usa_valor_fixo_por_kg_internacional(): void
    {
        $fornecedor = Fornecedor::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);
        $unidade = UnidadeNegocio::factory()->create(['id_estado' => Estado::ID_CEARA]);
        $fruta = Fruta::factory()->internacional()->comIcmsCeara([
            FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG => '0.00',
            FrutaIcmsLinhaFormulario::ENTRADA_INTERNACIONAL_KG => '0.26',
        ])->create(['kg_por_unidade_medicao' => 10]);

        $icmsKg = app(FrutaIcmsCalculoService::class)->calcularEntradaPorKg($fruta, $unidade, $fornecedor);

        $this->assertSame('0.26', $icmsKg);
    }

    public function test_saida_pe_percentual_dentro_e_fora_do_estado_nacional(): void
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

    public function test_saida_pe_fruta_internacional_usa_aliquotas_internacionais_distintas(): void
    {
        $unidadePe = UnidadeNegocio::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);
        $unidadeCe = UnidadeNegocio::factory()->create(['id_estado' => Estado::ID_CEARA]);
        $clienteDentro = Cliente::factory()->create(['id_unidade_negocio' => $unidadePe->id]);
        $clienteFora = Cliente::factory()->create(['id_unidade_negocio' => $unidadeCe->id]);

        $fruta = Fruta::factory()->internacional()->comIcmsPernambuco([
            FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT => '20.50',
            FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_FORA_PCT => '12.00',
            FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_DENTRO_PCT => '18.00',
            FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_FORA_PCT => '10.00',
        ])->create();

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

        $this->assertSame('180.00', $dentro['valor_icms_total']);
        $this->assertSame('100.00', $fora['valor_icms_total']);
    }

    public function test_sync_pe_persiste_quatro_aliquotas_percentuais(): void
    {
        $fruta = Fruta::factory()->create();

        app(FrutaIcmsSyncService::class)->syncEstado($fruta, Estado::ID_PERNAMBUCO, [
            FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT => '18.00',
            FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_FORA_PCT => '10.00',
        ]);

        $saidaNacDentro = FrutaIcmsAliquota::query()
            ->where('fruta_id', $fruta->id)
            ->where('id_estado', Estado::ID_PERNAMBUCO)
            ->where('operacao', FrutaIcmsOperacao::SAIDA)
            ->where('procedencia', FrutaProcedencia::NACIONAL)
            ->where('escopo_venda', FrutaIcmsEscopoVenda::DENTRO_ESTADO)
            ->firstOrFail();

        $saidaIntFora = FrutaIcmsAliquota::query()
            ->where('fruta_id', $fruta->id)
            ->where('id_estado', Estado::ID_PERNAMBUCO)
            ->where('operacao', FrutaIcmsOperacao::SAIDA)
            ->where('procedencia', FrutaProcedencia::INTERNACIONAL)
            ->where('escopo_venda', FrutaIcmsEscopoVenda::FORA_ESTADO)
            ->firstOrFail();

        $this->assertSame(FrutaIcmsTipoValor::PERCENTUAL, $saidaNacDentro->tipo_valor);
        $this->assertSame(FrutaIcmsTipoValor::PERCENTUAL, $saidaIntFora->tipo_valor);
        $this->assertSame('18.0000', (string) $saidaNacDentro->valor);
        $this->assertSame('10.0000', (string) $saidaIntFora->valor);
    }
}
