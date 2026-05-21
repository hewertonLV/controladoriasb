<?php

namespace Tests\Feature\Dashboard;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\FreteStatusSituacao;
use App\Enums\Permissions;
use App\Enums\Roles;
use App\Enums\TipoDevolucao;
use App\Models\Cliente;
use App\Models\Estado;
use App\Models\Fornecedor;
use App\Models\Frete;
use App\Models\Fruta;
use App\Models\HistoricoCOUnNg;
use App\Models\Movimentacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Services\Dashboard\DashboardFinanceiroService;
use Database\Seeders\CategoriaMovimentacaoSeeder;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\StatusMovimentacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class DashboardFinanceiroTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    public function test_dashboard_financeiro_exibe_cards_do_escopo_da_unidade(): void
    {
        $this->seedBase();
        $c = $this->cenarioBase();
        $this->registrarCompra($c, '10', '500,00');
        $venda = $this->registrarVenda($c, '4', '800,00');
        $this->registrarDevolucao($venda, TipoDevolucao::COM_RETORNO_ESTOQUE, '2');

        $user = User::factory()->create([
            'must_change_password' => false,
            'ativo' => true,
        ]);
        $user->unidadesNegocio()->sync([$c['unidade']->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('Total faturado')
            ->assertSee('Total rentabilidade')
            ->assertViewHas('financeiro', fn (array $financeiro): bool => $financeiro['cards']['faturado']['reais'] === 800.0
                && $financeiro['cards']['devolucao']['reais'] === 400.0
                && $financeiro['cards']['liquido']['reais'] === 400.0
                && $financeiro['cards']['rentabilidade']['percentual'] === 112.5);
    }

    public function test_filtro_por_unidade_restringe_totais(): void
    {
        $this->seedBase();
        $cA = $this->cenarioBase('UNIDADE A');
        $cB = $this->cenarioBase('UNIDADE B');

        $this->registrarCompra($cA, '10', '500,00');
        $this->registrarVenda($cA, '2', '200,00');

        $this->registrarCompra($cB, '10', '500,00');
        $this->registrarVenda($cB, '5', '1000,00');

        $user = User::factory()->create([
            'must_change_password' => false,
            'ativo' => true,
        ]);
        $user->unidadesNegocio()->sync([$cA['unidade']->id, $cB['unidade']->id]);

        $service = app(DashboardFinanceiroService::class);

        $todas = $service->forUser($user);
        $this->assertSame(1200.0, $todas['cards']['faturado']['reais']);

        $soA = $service->forUser($user, [$cA['unidade']->id]);
        $this->assertSame(200.0, $soA['cards']['faturado']['reais']);
    }

    public function test_administrador_ve_todas_unidades_no_filtro(): void
    {
        $this->seed(EstadoSeeder::class);
        UnidadeNegocio::factory()->count(2)->create();

        $admin = User::factory()->create([
            'must_change_password' => false,
            'ativo' => true,
        ]);
        $admin->assignRole(Role::findOrCreate(Roles::ADMINISTRADOR->value, 'web'));

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Unidades de negócio');
    }

    public function test_filtro_unidade_nao_permitida_retorna_erro_validacao(): void
    {
        $this->seed(EstadoSeeder::class);
        $unidadeA = UnidadeNegocio::factory()->create();
        $unidadeB = UnidadeNegocio::factory()->create();

        $user = User::factory()->create([
            'must_change_password' => false,
            'ativo' => true,
        ]);
        $user->unidadesNegocio()->sync([$unidadeA->id]);

        $this->actingAs($user)
            ->get(route('dashboard', ['unidades' => [$unidadeB->id]]))
            ->assertSessionHasErrors('unidades');
    }

    private function seedBase(): void
    {
        $this->seed(EstadoSeeder::class);
        $this->seed(CategoriaMovimentacaoSeeder::class);
        $this->seed(StatusMovimentacaoSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function cenarioBase(string $nomeUnidade = 'UNIDADE TESTE'): array
    {
        $fornecedor = Fornecedor::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);
        $cliente = Cliente::factory()->create();
        $unidade = UnidadeNegocio::factory()->create([
            'nome' => $nomeUnidade,
            'possui_estoque' => true,
            'id_estado' => Estado::ID_PERNAMBUCO,
            'custo_operacional' => 0,
            'is_hub' => false,
        ]);

        HistoricoCOUnNg::query()->where('id_unidade_negocio', $unidade->id)->update(['status_position' => false]);
        HistoricoCOUnNg::factory()->create(['id_unidade_negocio' => $unidade->id, 'custo_operacional' => 0, 'status_position' => true]);

        return [
            'empresa_fornecedor' => $fornecedor->registroCorporativo()->firstOrFail(),
            'empresa_cliente' => $cliente->registroCorporativo()->firstOrFail(),
            'empresa_unidade' => $unidade->registroCorporativo()->firstOrFail(),
            'unidade' => $unidade,
            'fruta' => Fruta::factory()->create(['kg_por_unidade_medicao' => 10]),
            'frete' => Frete::factory()->create(['valor' => '0.00', 'status_situacao' => FreteStatusSituacao::ABERTA->value]),
        ];
    }

    /**
     * @param  array<string, mixed>  $cenario
     */
    private function registrarCompra(array $cenario, string $qtdUm, string $valorNfTotal): Movimentacao
    {
        $this->actingAs($this->movimentacoesComprasUsuario())->postJson(route('admin.movimentacoes.compras.store'), [
            'id_empresa_origem' => $cenario['empresa_fornecedor']->id,
            'id_empresa_destino' => $cenario['empresa_unidade']->id,
            'id_fruta' => $cenario['fruta']->id,
            'qtd_fruta_um' => $qtdUm,
            'valor_nf_total' => $valorNfTotal,
            'id_frete' => $cenario['frete']->id,
        ])->assertCreated();

        return Movimentacao::query()->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Compra->value)->orderByDesc('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $cenario
     */
    private function registrarVenda(array $cenario, string $qtdUm, string $valorNfTotal): Movimentacao
    {
        $this->actingAs($this->movimentacoesVendasUsuario())->postJson(route('admin.movimentacoes.vendas.store'), [
            'numero_nf' => 'NF-VENDA-'.uniqid(),
            'id_empresa_origem' => $cenario['empresa_unidade']->id,
            'id_empresa_destino' => $cenario['empresa_cliente']->id,
            'itens' => [
                ['id_fruta' => $cenario['fruta']->id, 'qtd_fruta_um' => $qtdUm, 'valor_nf_total' => $valorNfTotal],
            ],
        ])->assertCreated();

        return Movimentacao::query()->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)->orderByDesc('id')->firstOrFail();
    }

    private function registrarDevolucao(Movimentacao $venda, TipoDevolucao $tipo, string $qtdUm): Movimentacao
    {
        $this->actingAs($this->movimentacoesDevolucoesUsuario())
            ->postJson(route('admin.movimentacoes.devolucoes.store'), [
                'movimentacao_venda_origem_id' => $venda->id,
                'tipo_devolucao' => $tipo->value,
                'qtd_fruta_um' => $qtdUm,
                'numero_nf_devolucao' => 'DEV-'.uniqid(),
                'motivo_devolucao' => 'Devolução de teste.',
                'id_unidade_negocio_retorno' => $venda->empresaOrigem->entidade->id,
            ])
            ->assertCreated();

        return Movimentacao::query()->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Devolucao->value)->orderByDesc('id')->firstOrFail();
    }
}
