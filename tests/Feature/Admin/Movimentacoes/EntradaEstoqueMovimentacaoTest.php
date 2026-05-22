<?php

namespace Tests\Feature\Admin\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\FrutaUmIcms;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\Permissions;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\HistoricoCOUnNg;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use Database\Seeders\CategoriaMovimentacaoSeeder;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\StatusMovimentacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class EntradaEstoqueMovimentacaoTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            EstadoSeeder::class,
            StatusMovimentacaoSeeder::class,
            CategoriaMovimentacaoSeeder::class,
            PermissionSeeder::class,
        ]);
    }

    public function test_criar_entrada_aumenta_estoque_e_recalcula_preco_medio(): void
    {
        [$unidade, $empresa, $fruta] = $this->criarUnidadeEFruta();

        $this->actingAs($this->userEntradaEstoque())->postJson(
            route('admin.movimentacoes.entradas-estoque.store'),
            [
                'id_empresa_origem' => $empresa->id,
                'itens' => [[
                    'id_fruta' => $fruta->id,
                    'qtd_fruta_um' => '10',
                    'preco_fruta_um' => '50,00',
                ]],
            ],
        )->assertCreated();

        $mov = Movimentacao::query()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::EntradaEstoque->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_ENTRADA)
            ->first();

        $this->assertNotNull($mov);
        $this->assertSame('500.00', (string) $mov->valor_nf_total);
        $this->assertSame('50.00', (string) $mov->valor_nf_um);
        $this->assertSame('10.00', (string) $mov->qtd_fruta_um);
        $this->assertSame('100.00', (string) $mov->qtd_fruta_kg);

        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->first();

        $this->assertNotNull($estoque);
        $this->assertSame('10.00', (string) $estoque->qtd_fruta_um);
        $this->assertSame('100.00', (string) $estoque->qtd_fruta_kg);
        $this->assertSame('500.00', (string) $estoque->valor_total_acumulado);
        $this->assertSame('5.00', (string) $estoque->preco_medio_kg);
    }

    public function test_segunda_entrada_media_ponderada_preco_medio(): void
    {
        [$unidade, $empresa, $fruta] = $this->criarUnidadeEFruta();
        $user = $this->userEntradaEstoque();

        $this->actingAs($user)->postJson(route('admin.movimentacoes.entradas-estoque.store'), [
            'id_empresa_origem' => $empresa->id,
            'itens' => [['id_fruta' => $fruta->id, 'qtd_fruta_um' => '10', 'preco_fruta_um' => '40,00']],
        ])->assertCreated();

        $this->actingAs($user)->postJson(route('admin.movimentacoes.entradas-estoque.store'), [
            'id_empresa_origem' => $empresa->id,
            'itens' => [['id_fruta' => $fruta->id, 'qtd_fruta_um' => '10', 'preco_fruta_um' => '60,00']],
        ])->assertCreated();

        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();

        $this->assertSame('20.00', (string) $estoque->qtd_fruta_um);
        $this->assertSame('1000.00', (string) $estoque->valor_total_acumulado);
        $this->assertSame('5.00', (string) $estoque->preco_medio_kg);
    }

    public function test_tela_criar_carrega(): void
    {
        $this->criarUnidadeEFruta();

        $this->actingAs($this->userEntradaEstoque())
            ->get(route('admin.movimentacoes.entradas-estoque.create'))
            ->assertOk()
            ->assertSee('Registrar entrada da produção', false);
    }

    public function test_rejeita_quantidade_um_decimal(): void
    {
        [, $empresa, $fruta] = $this->criarUnidadeEFruta();

        $this->actingAs($this->userEntradaEstoque())->postJson(
            route('admin.movimentacoes.entradas-estoque.store'),
            [
                'id_empresa_origem' => $empresa->id,
                'itens' => [[
                    'id_fruta' => $fruta->id,
                    'qtd_fruta_um' => '10,5',
                    'preco_fruta_um' => '50,00',
                ]],
            ],
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['itens.0.qtd_fruta_um']);
    }

    /**
     * @return array{0: UnidadeNegocio, 1: \App\Models\Empresa, 2: Fruta}
     */
    private function criarUnidadeEFruta(): array
    {
        $unidade = UnidadeNegocio::factory()->create([
            'possui_estoque' => true,
            'cpf_cnpj' => '11222333000181',
        ]);
        $empresa = $unidade->registroCorporativo()->firstOrFail();

        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => '0.00',
            'status_position' => true,
        ]);

        $fruta = Fruta::factory()->comIcmsCeara([
            'entrada_nacional' => 0,
            'entrada_externo' => 0,
            'entrada_um' => FrutaUmIcms::KG->value,
        ])->create([
            'kg_por_unidade_medicao' => '10.00',
            'unidade_medicao' => 'CAIXA',
        ]);

        return [$unidade, $empresa, $fruta];
    }

    private function userEntradaEstoque(): \App\Models\User
    {
        return $this->userWithPermissions([
            Permissions::MOVIMENTACOES_ENTRADAS_ESTOQUE_VISUALIZAR,
            Permissions::MOVIMENTACOES_ENTRADAS_ESTOQUE_CRIAR,
        ]);
    }
}
