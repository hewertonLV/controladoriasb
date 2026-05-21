<?php

namespace Tests\Feature\Dashboard;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\Permissions;
use App\Enums\Roles;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Estado;
use App\Models\Fruta;
use App\Models\HistoricoCOUnNg;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use Database\Seeders\CategoriaMovimentacaoSeeder;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\StatusMovimentacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class OlhoDeDeusTest extends TestCase
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
        ]);
    }

    public function test_usuario_sem_permissao_nao_acessa_olho_de_deus(): void
    {
        $user = User::factory()->create([
            'must_change_password' => false,
            'ativo' => true,
        ]);

        $this->actingAs($user)
            ->get(route('olho-de-deus.index'))
            ->assertForbidden();
    }

    public function test_poll_detecta_venda_com_preco_abaixo_do_custo(): void
    {
        $user = $this->usuarioOlhoDeDeus();
        [$unidade, $empresaUnidade] = $this->criarUnidadeComEmpresa('UNIDADE TESTE');
        $fruta = Fruta::factory()->comIcmsCeara()->create([
            'kg_por_unidade_medicao' => '10.00',
        ]);
        $clienteEmpresa = $this->criarClienteEmpresa($unidade);

        $venda = Movimentacao::factory()->create([
            'id_empresa_origem' => $empresaUnidade->id,
            'id_empresa_destino' => $clienteEmpresa->id,
            'id_fruta' => $fruta->id,
            'categoria_movimentacao_id' => CategoriaMovimentacaoTipo::Venda->value,
            'status_movimentacao_id' => StatusMovimentacao::ID_SAIDA,
            'status_registro' => MovimentacaoStatusRegistro::ATIVO->value,
            'data_movimentacao' => now(),
            'valor_nf_total' => '100.00',
            'valor_nf_um' => '5.00',
            'valor_nf_kg' => '0.40',
            'preco_medio_fruta_um' => '10.00',
            'preco_medio_fruta_kg' => '1.00',
            'valor_custo_saida' => '100.00',
            'resultado_movimentacao' => '-10.00',
            'valor_frete_kg' => '0.10',
            'qtd_fruta_um' => '10.00',
            'qtd_fruta_kg' => '100.00',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('olho-de-deus.poll', [
                'mes' => now()->format('Y-m'),
                'carga_inicial' => 1,
            ]));

        $response
            ->assertOk()
            ->assertJsonStructure(['server_time', 'proximo_poll_ms', 'alertas']);

        $tipos = collect($response->json('alertas'))->pluck('tipo')->all();

        $this->assertContains('venda_preco_abaixo_custo_kg', $tipos);
        $this->assertContains('rentabilidade_venda_negativa', $tipos);

        $ids = collect($response->json('alertas'))->pluck('movimentacao_id')->unique()->all();
        $this->assertContains($venda->id, $ids);
    }

    public function test_poll_respeita_throttle(): void
    {
        $user = $this->usuarioOlhoDeDeus();

        $this->actingAs($user);

        for ($i = 0; $i < 5; $i++) {
            $this->getJson(route('olho-de-deus.poll'));
        }

        $this->getJson(route('olho-de-deus.poll'))
            ->assertStatus(429);
    }

    private function usuarioOlhoDeDeus(): User
    {
        $user = $this->userWithPermissions([Permissions::OLHO_DE_DEUS_VISUALIZAR]);
        $user->assignRole(Role::findOrCreate(Roles::ADMINISTRADOR->value, 'web'));

        return $user;
    }

    /**
     * @return array{0: UnidadeNegocio, 1: Empresa}
     */
    private function criarUnidadeComEmpresa(string $nome): array
    {
        $unidade = UnidadeNegocio::factory()->create([
            'nome' => $nome,
            'possui_estoque' => true,
            'id_estado' => Estado::ID_PERNAMBUCO,
        ]);

        HistoricoCOUnNg::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'custo_operacional' => '1.00',
            'status_position' => true,
        ]);

        $empresa = Empresa::query()->firstOrCreate(
            [
                'entidade_type' => UnidadeNegocio::class,
                'entidade_id' => $unidade->id,
            ],
        );

        return [$unidade, $empresa];
    }

    private function criarClienteEmpresa(UnidadeNegocio $unidade): Empresa
    {
        $cliente = Cliente::factory()->create([
            'id_unidade_negocio' => $unidade->id,
        ]);

        return Empresa::query()->firstOrCreate(
            [
                'entidade_type' => Cliente::class,
                'entidade_id' => $cliente->id,
            ],
        );
    }
}
