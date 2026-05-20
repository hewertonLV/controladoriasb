<?php

namespace Tests\Feature\Admin\Frutas;

use App\Enums\FrutaUmIcms;
use App\Models\Fruta;
use App\Services\Frutas\FrutaIcmsPlanilhaNormalizer;
use App\Support\Frutas\FrutaIcmsLinhaFormulario;
use Database\Seeders\EstadoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Testes do FrutaIcmsPlanilhaNormalizer (ADR-0014 + ADR-0026).
 *
 * Layout: A B C D E F G H I J  [K opcional]
 *         id est cn ucn ce uce vf uvf vd uvd tipo_estado
 */
class FrutaIcmsPlanilhaNormalizerTest extends TestCase
{
    use RefreshDatabase;

    private FrutaIcmsPlanilhaNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(EstadoSeeder::class);
        Fruta::factory()->create(['id_cigam' => '010172']);
        $this->normalizer = app(FrutaIcmsPlanilhaNormalizer::class);
    }

    // ──────────────────────────────────────────────
    // Coluna K ausente — modo legado (10 colunas)
    // ──────────────────────────────────────────────

    public function test_legado_10_colunas_ce_real_funciona(): void
    {
        $row = ['010172', 'ce', '0.26', 'KG', '0.08', 'KG', '0.00', 'KG', '0.00', 'KG'];

        $result = $this->normalizer->normalize($row);

        $this->assertSame([], $result['erros']);
        $this->assertSame('0.26', $result['dados']['compra_nacional']);
        $this->assertSame(FrutaUmIcms::KG->value, $result['dados']['um_compra_nacional']);
        $this->assertSame(FrutaUmIcms::KG->value, $result['dados']['um_venda_importada']);
    }

    public function test_legado_10_colunas_pe_pct_na_venda_aceito(): void
    {
        $row = ['010172', 'PE', '0.00', 'KG', '0.00', 'KG', '20.50', 'PCT', '12.00', 'PCT'];

        $result = $this->normalizer->normalize($row);

        $this->assertSame([], $result['erros']);
        $this->assertSame(FrutaUmIcms::PCT->value, $result['dados']['um_venda_importada']);
        $this->assertSame(FrutaUmIcms::PCT->value, $result['dados']['um_venda_nacional']);
        $this->assertSame('20.50', $result['dados']['venda_nacional']);
        $this->assertSame('12.00', $result['dados']['venda_importada']);
    }

    public function test_legado_10_colunas_pct_na_compra_gera_erro(): void
    {
        // Problema original das linhas PE: PCT em D/F é inválido para entrada
        $row = ['010172', 'PE', '0.00', 'PCT', '0.00', 'PCT', '0.00', 'PCT', '0.00', 'PCT'];

        $result = $this->normalizer->normalize($row);

        $this->assertNotEmpty($result['erros']);
        $erros = implode(' ', $result['erros']);
        $this->assertStringContainsString('UM compra nacional', $erros);
        $this->assertStringContainsString('UM compra exterior', $erros);
    }

    // ──────────────────────────────────────────────
    // Coluna K = REAL (11 colunas)
    // ──────────────────────────────────────────────

    public function test_11_colunas_k_real_ce_usa_um_das_colunas_h_j(): void
    {
        $row = ['010172', 'ce', '0.26', 'KG', '0.08', 'KG', '0.00', 'KG', '0.00', 'KG', 'REAL'];

        $result = $this->normalizer->normalize($row);

        $this->assertSame([], $result['erros']);
        $this->assertSame(FrutaUmIcms::KG->value, $result['dados']['um_venda_importada']);
        $this->assertSame(FrutaUmIcms::KG->value, $result['dados']['um_venda_nacional']);
    }

    public function test_11_colunas_k_real_com_um_unidade_medicao(): void
    {
        $row = ['010172', 'ce', '0.12', 'UM', '0.00', 'KG', '0.00', 'KG', '0.00', 'KG', 'REAL'];

        $result = $this->normalizer->normalize($row);

        $this->assertSame([], $result['erros']);
        $this->assertSame(FrutaUmIcms::UM->value, $result['dados']['um_compra_nacional']);
    }

    // ──────────────────────────────────────────────
    // Coluna K = PCT (11 colunas) — PE venda percentual
    // ──────────────────────────────────────────────

    public function test_11_colunas_k_pct_pe_forca_um_venda_para_pct(): void
    {
        // Com K=PCT, H/J são irrelevantes — normalizer força PCT
        $row = ['010172', 'PE', '0.00', 'KG', '0.00', 'KG', '20.50', 'KG', '12.00', 'KG', 'PCT'];

        $result = $this->normalizer->normalize($row);

        $this->assertSame([], $result['erros']);
        $this->assertSame(FrutaUmIcms::PCT->value, $result['dados']['um_venda_importada']);
        $this->assertSame(FrutaUmIcms::PCT->value, $result['dados']['um_venda_nacional']);
        $this->assertSame('20.50', $result['dados']['venda_nacional']);
        $this->assertSame('12.00', $result['dados']['venda_importada']);
    }

    public function test_11_colunas_k_pct_compra_mantém_um_kg(): void
    {
        // K=PCT não afeta compra — UM de compra continua KG
        $row = ['010172', 'PE', '0.00', 'KG', '0.00', 'KG', '20.50', 'PCT', '12.00', 'PCT', 'PCT'];

        $result = $this->normalizer->normalize($row);

        $this->assertSame([], $result['erros']);
        $this->assertSame(FrutaUmIcms::KG->value, $result['dados']['um_compra_nacional']);
        $this->assertSame(FrutaUmIcms::KG->value, $result['dados']['um_compra_exterior']);
    }

    public function test_11_colunas_k_pct_pe_valores_zerados_aceito(): void
    {
        $row = ['010172', 'PE', '0.00', 'KG', '0.00', 'KG', '0.00', 'PCT', '0.00', 'PCT', 'PCT'];

        $result = $this->normalizer->normalize($row);

        $this->assertSame([], $result['erros']);
        $this->assertSame(FrutaUmIcms::PCT->value, $result['dados']['um_venda_importada']);
    }

    // ──────────────────────────────────────────────
    // Coluna K inválida
    // ──────────────────────────────────────────────

    public function test_11_colunas_k_invalido_gera_erro(): void
    {
        $row = ['010172', 'ce', '0.26', 'KG', '0.08', 'KG', '0.00', 'KG', '0.00', 'KG', 'INVALIDO'];

        $result = $this->normalizer->normalize($row);

        $erros = implode(' ', $result['erros']);
        $this->assertStringContainsString('coluna K', $erros);
    }

    // ──────────────────────────────────────────────
    // UM de compra: PCT sempre inválido (K não muda isso)
    // ──────────────────────────────────────────────

    public function test_pct_em_um_compra_nacional_é_sempre_inválido(): void
    {
        $row = ['010172', 'PE', '0.00', 'PCT', '0.00', 'KG', '0.00', 'PCT', '0.00', 'PCT', 'PCT'];

        $result = $this->normalizer->normalize($row);

        $erros = implode(' ', $result['erros']);
        $this->assertStringContainsString('UM compra nacional', $erros);
    }

    public function test_layout_novo_8_colunas_permite_internacional_distinto(): void
    {
        $row = ['010172', 'PE', '0.00', '0.00', '20.50', '12.00', '18.00', '10.00'];

        $result = $this->normalizer->normalize($row);

        $this->assertSame([], $result['erros']);
        $this->assertSame('20.50', $result['dados'][FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT]);
        $this->assertSame('12.00', $result['dados'][FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_FORA_PCT]);
        $this->assertSame('18.00', $result['dados'][FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_DENTRO_PCT]);
        $this->assertSame('10.00', $result['dados'][FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_FORA_PCT]);
    }
}
