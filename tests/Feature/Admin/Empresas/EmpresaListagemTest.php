<?php

namespace Tests\Feature\Admin\Empresas;

use App\Enums\Permissions;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Fornecedor;
use App\Models\UnidadeNegocio;

class EmpresaListagemTest extends EmpresasTestCase
{
    public function test_listagem_carrega(): void
    {
        $user = $this->userWithPermissions([Permissions::EMPRESAS_VISUALIZAR]);
        Cliente::factory()->create([
            'razao_social' => 'Empresa Visível',
            'fantasia' => null,
        ]);

        $this->actingAs($user)
            ->get(route('admin.empresas.index'))
            ->assertOk()
            ->assertSee('Hub corporativo')
            ->assertSee('EMPRESA VISÍVEL');
    }

    public function test_pesquisa_razao_social_id_cigam_e_cnpj(): void
    {
        $user = $this->userWithPermissions([Permissions::EMPRESAS_VISUALIZAR]);
        Fornecedor::factory()->create([
            'id_cigam' => '7001',
            'razao_social' => 'Alpha Nome',
            'fantasia' => 'Alpha Fantasia',
            'cnpj_cpf' => '12345678000190',
        ]);
        Fornecedor::factory()->create([
            'id_cigam' => '7002',
            'razao_social' => 'Beta Nome',
            'fantasia' => 'Beta Fantasia',
            'cnpj_cpf' => '98765432000100',
        ]);

        foreach (['Alpha Nome', 'Alpha Fantasia', '7001', '12.345.678/0001-90'] as $search) {
            $this->actingAs($user)
                ->get(route('admin.empresas.index', ['search' => $search]), ['X-Requested-With' => 'XMLHttpRequest'])
                ->assertOk()
                ->assertSee('ALPHA FANTASIA')
                ->assertDontSee('BETA FANTASIA');
        }
    }

    public function test_filtro_status_listagem(): void
    {
        $user = $this->userWithPermissions([Permissions::EMPRESAS_VISUALIZAR]);
        Cliente::factory()->create([
            'razao_social' => 'Cliente Sempre Ativo',
            'fantasia' => null,
        ]);
        UnidadeNegocio::factory()->create(['nome' => 'Un Ativa', 'status' => true]);
        UnidadeNegocio::factory()->create(['nome' => 'Un Inativa', 'status' => false]);

        $this->actingAs($user)
            ->get(route('admin.empresas.index', ['status' => '1']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('CLIENTE SEMPRE ATIVO')
            ->assertSee('UN ATIVA')
            ->assertDontSee('UN INATIVA');

        $this->actingAs($user)
            ->get(route('admin.empresas.index', ['status' => '0']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSee('UN INATIVA')
            ->assertDontSee('UN ATIVA')
            ->assertDontSee('CLIENTE SEMPRE ATIVO');
    }

    public function test_paginacao_respeita_per_page_e_all(): void
    {
        $user = $this->userWithPermissions([Permissions::EMPRESAS_VISUALIZAR]);
        foreach (range('A', 'K') as $letra) {
            Empresa::factory()->create();
        }

        $this->actingAs($user)
            ->get(route('admin.empresas.index', ['per_page' => 10, 'sort' => 'nome_exibicao']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSeeInOrder(['Exibindo', '<strong>10</strong>', 'de', '<strong>11</strong>'], false);

        $this->actingAs($user)
            ->get(route('admin.empresas.index', ['per_page' => 'all', 'sort' => 'nome_exibicao']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSeeInOrder(['Exibindo', '<strong>11</strong>', 'de', '<strong>11</strong>'], false);
    }

    public function test_ordenacao_por_nome(): void
    {
        $user = $this->userWithPermissions([Permissions::EMPRESAS_VISUALIZAR]);
        Cliente::factory()->create(['razao_social' => 'Zulu', 'fantasia' => null]);
        Cliente::factory()->create(['razao_social' => 'Alpha', 'fantasia' => null]);
        Cliente::factory()->create(['razao_social' => 'Mike', 'fantasia' => null]);

        $this->actingAs($user)
            ->get(route('admin.empresas.index', ['sort' => 'nome_exibicao', 'direction' => 'asc']), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSeeInOrder(['ALPHA', 'MIKE', 'ZULU']);
    }

    public function test_ordenacao_numerica_por_id_cigam(): void
    {
        $user = $this->userWithPermissions([Permissions::EMPRESAS_VISUALIZAR]);

        foreach (['1000', '100', '20', '10', '2', '1'] as $idCigam) {
            Cliente::factory()->create([
                'id_cigam' => $idCigam,
                'razao_social' => 'Empresa '.$idCigam,
            ]);
        }

        $this->actingAs($user)
            ->get(route('admin.empresas.index', [
                'sort' => 'id_cigam',
                'direction' => 'asc',
                'per_page' => 'all',
            ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSeeInOrder([
                '<code>000001</code>',
                '<code>000002</code>',
                '<code>000010</code>',
                '<code>000020</code>',
                '<code>000100</code>',
                '<code>001000</code>',
            ], false);

        $this->actingAs($user)
            ->get(route('admin.empresas.index', [
                'sort' => 'id_cigam',
                'direction' => 'desc',
                'per_page' => 'all',
            ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSeeInOrder([
                '<code>001000</code>',
                '<code>000100</code>',
                '<code>000020</code>',
                '<code>000010</code>',
                '<code>000002</code>',
                '<code>000001</code>',
            ], false);
    }

    public function test_busca_paginacao_per_page_e_ordenacao_coexistem(): void
    {
        $user = $this->userWithPermissions([Permissions::EMPRESAS_VISUALIZAR]);
        foreach (['10', '2', '1'] as $idCigam) {
            Cliente::factory()->create([
                'id_cigam' => $idCigam,
                'razao_social' => 'Cliente Comum '.$idCigam,
            ]);
        }
        Cliente::factory()->create(['id_cigam' => '100', 'razao_social' => 'Outro Cliente']);

        $this->actingAs($user)
            ->get(route('admin.empresas.index', [
                'search' => 'Cliente Comum',
                'per_page' => 2,
                'sort' => 'id_cigam',
                'direction' => 'asc',
            ]), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertSeeInOrder(['<code>000001</code>', '<code>000002</code>'], false)
            ->assertDontSee('OUTRO CLIENTE');
    }
}
