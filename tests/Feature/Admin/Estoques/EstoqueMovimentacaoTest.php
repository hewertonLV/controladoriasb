<?php

namespace Tests\Feature\Admin\Estoques;

use App\Enums\Permissions;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Services\Estoques\EstoqueMovimentacaoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class EstoqueMovimentacaoTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    public function test_entrada_atualiza_estoque_e_cria_movimentacao(): void
    {
        $unidade = UnidadeNegocio::factory()->create(['possui_estoque' => true]);
        $fruta = Fruta::factory()->create(['kg_por_unidade_medicao' => '20.00']);

        $service = app(EstoqueMovimentacaoService::class);
        $mov = $service->movimentarPorTipo($unidade, $fruta, 'entrada', '100', '2.50');

        $this->assertTrue($mov->status_ultima_posicao);

        $estoque = $unidade->estoques()->where('id_fruta', $fruta->id)->firstOrFail();
        $this->assertSame('100.00', (string) $estoque->qtd_fruta_kg);
        $this->assertSame('5.00', (string) $estoque->qtd_fruta_um);
        $this->assertSame('2.50', (string) $estoque->preco_medio_kg);
        $this->assertSame('50.00', (string) $estoque->preco_medio_um);
        $this->assertSame('250.00', (string) $estoque->valor_total_acumulado);
    }

    public function test_movimentar_requer_permissao(): void
    {
        $unidade = UnidadeNegocio::factory()->create(['possui_estoque' => true]);
        $fruta = Fruta::factory()->create();

        $this->actingAs($this->userWithoutEmpresaPermissions())
            ->get(route('admin.estoques.movimentar'))
            ->assertForbidden();

        $this->actingAs($this->userWithPermissions([Permissions::ESTOQUES_VISUALIZAR]))
            ->post(route('admin.estoques.movimentar.store'), [
                'id_unidade_negocio' => $unidade->id,
                'id_fruta' => $fruta->id,
                'tipo' => 'entrada',
                'quantidade_kg' => '10',
                'preco_medio_kg' => '1',
            ])
            ->assertForbidden();
    }
}
