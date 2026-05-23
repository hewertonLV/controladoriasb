<?php

namespace Tests\Unit\Support;

use App\Enums\FrutaUnidadeMedicao;
use App\Models\Fruta;
use App\Support\Movimentacoes\VendaImportacaoQuantidade;
use Database\Seeders\EstadoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendaImportacaoQuantidadeTest extends TestCase
{
    use RefreshDatabase;

    public function test_mantem_quantidade_quando_um_da_planilha_igual_a_da_fruta(): void
    {
        $this->seed(EstadoSeeder::class);

        $fruta = Fruta::factory()->create([
            'unidade_medicao' => FrutaUnidadeMedicao::CAIXA->value,
            'kg_por_unidade_medicao' => '10.00',
        ]);

        $resultado = VendaImportacaoQuantidade::normalizar($fruta, '3.00', FrutaUnidadeMedicao::CAIXA->value);

        $this->assertSame([
            'qtd_planilha' => '3.00',
            'unidade_medicao_planilha' => 'CAIXA',
            'qtd_fruta_um' => '3.00',
            'unidade_medicao_fruta' => 'CAIXA',
        ], $resultado);
    }

    public function test_converte_kg_da_planilha_para_um_cadastrada_da_fruta(): void
    {
        $this->seed(EstadoSeeder::class);

        $fruta = Fruta::factory()->create([
            'unidade_medicao' => FrutaUnidadeMedicao::CAIXA->value,
            'kg_por_unidade_medicao' => '10.00',
        ]);

        $resultado = VendaImportacaoQuantidade::normalizar($fruta, '25.00', FrutaUnidadeMedicao::KG->value);

        $this->assertSame('25.00', $resultado['qtd_planilha']);
        $this->assertSame('KG', $resultado['unidade_medicao_planilha']);
        $this->assertSame('2.50', $resultado['qtd_fruta_um']);
        $this->assertSame('CAIXA', $resultado['unidade_medicao_fruta']);
    }

    public function test_rejeita_um_diferente_que_nao_seja_kg(): void
    {
        $this->seed(EstadoSeeder::class);

        $fruta = Fruta::factory()->create([
            'unidade_medicao' => FrutaUnidadeMedicao::CAIXA->value,
            'kg_por_unidade_medicao' => '10.00',
        ]);

        $this->assertNull(
            VendaImportacaoQuantidade::normalizar($fruta, '2.00', FrutaUnidadeMedicao::PACOTE->value),
        );
    }
}
