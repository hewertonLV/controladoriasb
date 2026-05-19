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

    public function test_index_exibe_tabela_de_unidades_e_lista_apenas_unidade_selecionada(): void
    {
        $unidadeA = UnidadeNegocio::factory()->create(['nome' => 'UNIDADE ESTOQUE A', 'possui_estoque' => true]);
        $unidadeB = UnidadeNegocio::factory()->create(['nome' => 'UNIDADE ESTOQUE B', 'possui_estoque' => true]);
        $frutaA = Fruta::factory()->create(['nome' => 'FRUTA ESTOQUE A', 'kg_por_unidade_medicao' => '10.00']);
        $frutaB = Fruta::factory()->create(['nome' => 'FRUTA ESTOQUE B', 'kg_por_unidade_medicao' => '10.00']);

        $service = app(EstoqueMovimentacaoService::class);
        $service->movimentarPorTipo($unidadeA, $frutaA, 'entrada', '100', '2.00');
        $service->movimentarPorTipo($unidadeB, $frutaB, 'entrada', '50', '3.00');

        $user = $this->userWithPermissions([Permissions::ESTOQUES_VISUALIZAR]);

        $this->actingAs($user)
            ->get(route('admin.estoques.index'))
            ->assertOk()
            ->assertSeeText('UNIDADE ESTOQUE A')
            ->assertSeeText('UNIDADE ESTOQUE B')
            ->assertSee('id="estoques-unidades-datatable"', false)
            ->assertSee('data-admin-datatable', false)
            ->assertSeeText('100,00')
            ->assertSeeText('Abrir')
            ->assertSee(route('admin.estoques.unidade', $unidadeA), false)
            ->assertDontSeeText('FRUTA ESTOQUE A')
            ->assertDontSeeText('FRUTA ESTOQUE B');

        $this->actingAs($user)
            ->get(route('admin.estoques.unidade', $unidadeA))
            ->assertOk()
            ->assertSeeText('Estoque — UNIDADE ESTOQUE A')
            ->assertSeeText('FRUTA ESTOQUE A')
            ->assertDontSeeText('FRUTA ESTOQUE B')
            ->assertSee('estoques-datatable', false)
            ->assertSee('fornecedores-datatable-card', false)
            ->assertSee('fornecedores-table', false)
            ->assertSee('fornecedores-datatable-toolbar', false)
            ->assertSee('fornecedor-action-link', false)
            ->assertSee('estoques-table-container', false)
            ->assertSee('table-layout: fixed', false)
            ->assertSee('overflow-x: visible', false)
            ->assertDontSee('<div class="table-responsive">', false)
            ->assertSee('<th># CI.</th>', false)
            ->assertSee('<th>Qtd.</th>', false)
            ->assertSee('<th>Custo</th>', false)
            ->assertSee('assets/vendor/datatables.net-buttons/js/dataTables.buttons.min.js', false)
            ->assertSee('searchPlaceholder: \'Fruta, ID CIGAM, quantidade ou valor\'', false)
            ->assertSee('text: \'Copiar\'', false)
            ->assertSee('text: \'Imprimir\'', false)
            ->assertDontSee('estoques-filtro-fruta', false);
    }
}
