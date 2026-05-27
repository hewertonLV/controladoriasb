<?php

namespace Tests\Unit\Frutas;

use App\Enums\FrutaUnidadeMedicao;
use App\Services\Frutas\FrutaPlanilhaNormalizer;
use PHPUnit\Framework\TestCase;

class FrutaPlanilhaNormalizerTest extends TestCase
{
    public function test_pct_mapeia_para_um_pct_e_kg_com_tres_casas(): void
    {
        $normalizer = new FrutaPlanilhaNormalizer;

        $resultado = $normalizer->normalize(['100', 'UVA PACOTE', 'PCT', '0,35']);

        $this->assertSame([], $resultado['erros']);
        $this->assertSame(FrutaUnidadeMedicao::PCT->value, $resultado['dados']['unidade_medicao']);
        $this->assertSame('0.350', $resultado['dados']['kg_por_unidade_medicao']);
    }

    public function test_pc_continua_mapeando_para_pacote(): void
    {
        $this->assertSame(
            FrutaUnidadeMedicao::PACOTE->value,
            FrutaPlanilhaNormalizer::normalizarUnidadeMedicao('PC'),
        );
    }

    public function test_bdj_e_bandeja_mapeiam_para_bdj(): void
    {
        $normalizer = new FrutaPlanilhaNormalizer;

        $bdj = $normalizer->normalize(['200', 'MORANGO BANDEJA', 'BDJ', '1,2']);
        $bandeja = $normalizer->normalize(['201', 'UVA BANDEJA', 'BANDEJA', '0,85']);

        $this->assertSame(FrutaUnidadeMedicao::BDJ->value, $bdj['dados']['unidade_medicao']);
        $this->assertSame('1.20', $bdj['dados']['kg_por_unidade_medicao']);
        $this->assertSame(FrutaUnidadeMedicao::BDJ->value, $bandeja['dados']['unidade_medicao']);
        $this->assertSame('0.85', $bandeja['dados']['kg_por_unidade_medicao']);
    }

    public function test_kg_com_ponto_e_espacos_normaliza_para_kg(): void
    {
        $this->assertSame(
            FrutaUnidadeMedicao::KG->value,
            FrutaPlanilhaNormalizer::normalizarUnidadeMedicao(' kg. '),
        );
    }

    public function test_kg_e_quilograma_mapeiam_para_kg_com_tres_casas(): void
    {
        $normalizer = new FrutaPlanilhaNormalizer;

        $kg = $normalizer->normalize(['300', 'BANANA KG', 'KG', '1']);
        $quilo = $normalizer->normalize(['301', 'MANGA', 'QUILOGRAMA', '1,5']);

        $this->assertSame(FrutaUnidadeMedicao::KG->value, $kg['dados']['unidade_medicao']);
        $this->assertSame('1.000', $kg['dados']['kg_por_unidade_medicao']);
        $this->assertSame(FrutaUnidadeMedicao::KG->value, $quilo['dados']['unidade_medicao']);
        $this->assertSame('1.500', $quilo['dados']['kg_por_unidade_medicao']);
    }
}
