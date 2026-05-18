<?php

namespace Tests\Feature\Admin\Movimentacoes;

use App\Enums\FrutaUmIcms;
use App\Enums\Permissions;
use App\Enums\Roles;
use App\Models\Estado;
use App\Models\Estoque;
use App\Models\Fornecedor;
use App\Models\Frete;
use App\Models\Fruta;
use App\Models\HistoricoCOUnNg;
use App\Models\MovimentacaoEstoque;
use App\Models\UnidadeNegocio;
use Database\Seeders\CategoriaMovimentacaoSeeder;
use Database\Seeders\EstadoSeeder;
use Database\Seeders\StatusMovimentacaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class PermissaoUnidadeNegocioMovimentacaoTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    private function seedBase(): void
    {
        $this->seed([
            EstadoSeeder::class,
            StatusMovimentacaoSeeder::class,
            CategoriaMovimentacaoSeeder::class,
        ]);
    }

    private function criarUnidadeComEstoque(string $nome): array
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

        $fruta = Fruta::factory()->create([
            'kg_por_unidade_medicao' => '10.00',
            'icms_na_compra' => '0.00',
            'icms_ex_compra' => '0.00',
            'um_icms' => FrutaUmIcms::KG->value,
        ]);

        $estoque = Estoque::factory()->create([
            'id_unidade_negocio' => $unidade->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_kg' => '100.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_acumulado' => '500.00',
        ]);

        MovimentacaoEstoque::query()->create([
            'id_estoque' => $estoque->id,
            'id_unidade_negocio' => $unidade->id,
            'id_fruta' => $fruta->id,
            'qtd_fruta_kg' => '100.00',
            'qtd_fruta_um' => '10.00',
            'preco_medio_kg' => '5.00',
            'preco_medio_um' => '50.00',
            'valor_total_acumulado' => '500.00',
            'status_ultima_posicao' => true,
        ]);

        return [$unidade, $unidade->registroCorporativo()->firstOrFail(), $fruta];
    }

    public function test_usuario_sem_vinculo_nao_pode_comprar_para_unidade(): void
    {
        $this->seedBase();
        $fornecedor = Fornecedor::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);
        $empresaFornecedor = $fornecedor->registroCorporativo()->firstOrFail();
        [$unidade, $empresaUnidade, $fruta] = $this->criarUnidadeComEstoque('POLO A');
        $frete = Frete::factory()->create(['valor' => '10.00']);
        $user = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]);
        $user->unidadesNegocio()->detach();

        $this->actingAs($user)
            ->postJson(route('admin.movimentacoes.compras.store'), [
                'id_empresa_origem' => $empresaFornecedor->id,
                'id_empresa_destino' => $empresaUnidade->id,
                'id_fruta' => $fruta->id,
                'qtd_fruta_um' => '1',
                'valor_nf_total' => '100.00',
                'id_frete' => $frete->id,
            ])
            ->assertJsonValidationErrors(['id_empresa_destino'])
            ->assertJsonPath('errors.id_empresa_destino.0', 'Você não possui permissão para movimentar esta Unidade de Negócio.');

        $this->assertFalse($user->fresh()->podeMovimentarUnidade($unidade->id));
    }

    public function test_usuario_vinculado_pode_comprar_no_polo_a_e_nao_no_polo_b(): void
    {
        $this->seedBase();
        $fornecedor = Fornecedor::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);
        $empresaFornecedor = $fornecedor->registroCorporativo()->firstOrFail();
        [$unidadeA, $empresaA, $frutaA] = $this->criarUnidadeComEstoque('POLO A');
        [$unidadeB, $empresaB, $frutaB] = $this->criarUnidadeComEstoque('POLO B');
        $frete = Frete::factory()->create(['valor' => '10.00']);
        $user = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]);
        $user->unidadesNegocio()->sync([$unidadeA->id]);

        $this->actingAs($user)
            ->postJson(route('admin.movimentacoes.compras.store'), [
                'id_empresa_origem' => $empresaFornecedor->id,
                'id_empresa_destino' => $empresaA->id,
                'id_fruta' => $frutaA->id,
                'qtd_fruta_um' => '1',
                'valor_nf_total' => '100.00',
                'id_frete' => $frete->id,
            ])
            ->assertCreated();

        $this->actingAs($user)
            ->postJson(route('admin.movimentacoes.compras.store'), [
                'id_empresa_origem' => $empresaFornecedor->id,
                'id_empresa_destino' => $empresaB->id,
                'id_fruta' => $frutaB->id,
                'qtd_fruta_um' => '1',
                'valor_nf_total' => '100.00',
                'id_frete' => $frete->id,
            ])
            ->assertJsonValidationErrors(['id_empresa_destino']);

        $this->assertFalse($user->fresh()->podeMovimentarUnidade($unidadeB->id));
    }

    public function test_transferencia_valida_unidade_de_origem(): void
    {
        $this->seedBase();
        [$unidadeA, $empresaA, $frutaA] = $this->criarUnidadeComEstoque('POLO A');
        [, $empresaB, $frutaB] = $this->criarUnidadeComEstoque('POLO B');
        [, $empresaC] = $this->criarUnidadeComEstoque('POLO C');
        $user = $this->userWithPermissions([Permissions::MOVIMENTACOES_TRANSFERENCIAS_CRIAR]);
        $user->unidadesNegocio()->sync([$unidadeA->id]);

        $this->actingAs($user)
            ->postJson(route('admin.movimentacoes.transferencias.store'), [
                'id_empresa_origem' => $empresaA->id,
                'id_empresa_destino' => $empresaC->id,
                'id_fruta' => $frutaA->id,
                'qtd_fruta_um' => '1',
            ])
            ->assertCreated();

        $this->actingAs($user)
            ->postJson(route('admin.movimentacoes.transferencias.store'), [
                'id_empresa_origem' => $empresaB->id,
                'id_empresa_destino' => $empresaC->id,
                'id_fruta' => $frutaB->id,
                'qtd_fruta_um' => '1',
            ])
            ->assertJsonValidationErrors(['id_empresa_origem']);
    }

    public function test_administrador_acessa_todas_as_unidades_sem_vinculo(): void
    {
        $this->seedBase();
        $fornecedor = Fornecedor::factory()->create(['id_estado' => Estado::ID_PERNAMBUCO]);
        $empresaFornecedor = $fornecedor->registroCorporativo()->firstOrFail();
        [$unidade, $empresaUnidade, $fruta] = $this->criarUnidadeComEstoque('POLO ADMIN');
        $frete = Frete::factory()->create(['valor' => '10.00']);
        $user = $this->userWithPermissions([Permissions::MOVIMENTACOES_COMPRAS_CRIAR]);
        $user->assignRole(Role::findOrCreate(Roles::ADMINISTRADOR->value, 'web'));
        $user->unidadesNegocio()->detach();

        $this->actingAs($user)
            ->postJson(route('admin.movimentacoes.compras.store'), [
                'id_empresa_origem' => $empresaFornecedor->id,
                'id_empresa_destino' => $empresaUnidade->id,
                'id_fruta' => $fruta->id,
                'qtd_fruta_um' => '1',
                'valor_nf_total' => '100.00',
                'id_frete' => $frete->id,
            ])
            ->assertCreated();

        $this->assertTrue($user->fresh()->podeMovimentarUnidade($unidade->id));
    }

    public function test_select_de_compra_exibe_apenas_unidades_permitidas(): void
    {
        $this->seedBase();
        [$unidadeA] = $this->criarUnidadeComEstoque('POLO A');
        $this->criarUnidadeComEstoque('POLO B');
        $user = $this->userWithPermissions([
            Permissions::MOVIMENTACOES_COMPRAS_VISUALIZAR,
            Permissions::MOVIMENTACOES_COMPRAS_CRIAR,
        ]);
        $user->unidadesNegocio()->sync([$unidadeA->id]);

        $this->actingAs($user)
            ->get(route('admin.movimentacoes.compras.create'))
            ->assertOk()
            ->assertSee('POLO A')
            ->assertDontSee('POLO B');
    }
}
