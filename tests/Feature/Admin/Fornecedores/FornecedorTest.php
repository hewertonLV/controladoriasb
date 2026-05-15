<?php

namespace Tests\Feature\Admin\Fornecedores;

use App\Enums\Permissions;
use App\Models\Estado;
use App\Models\Fornecedor;

class FornecedorTest extends FornecedorTestCase
{
    public function test_convidado_e_redirecionado_para_login_na_listagem(): void
    {
        $this->get(route('admin.fornecedores.index'))
            ->assertRedirect(route('login'));
    }

    public function test_usuario_sem_permissao_recebe_403_na_listagem(): void
    {
        $this->actingAs($this->userWithoutEmpresaPermissions())
            ->get(route('admin.fornecedores.index'))
            ->assertForbidden();
    }

    public function test_usuario_com_visualizar_acessa_listagem(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::FORNECEDORES_VISUALIZAR]))
            ->get(route('admin.fornecedores.index'))
            ->assertOk();
    }

    public function test_listagem_ajax_retorna_partial_da_tabela(): void
    {
        Fornecedor::factory()->create(['razao_social' => 'FORNECEDOR AJAX']);

        $this->actingAs($this->fornecedoresManager())
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'text/html',
            ])
            ->get(route('admin.fornecedores.index'))
            ->assertOk()
            ->assertSee('FORNECEDOR AJAX', false);
    }

    public function test_cadastro_com_sucesso_normaliza_campos(): void
    {
        $payload = $this->fornecedorPayload([
            'id_cigam' => '1.2-3',
            'razao_social' => 'razão em minúsculas',
            'fantasia' => 'fant / min',
            'cnpj_cpf' => '11.222.333/0001-81',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::FORNECEDORES_CRIAR]))
            ->post(route('admin.fornecedores.store'), $payload)
            ->assertRedirect(route('admin.fornecedores.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('fornecedores', [
            'id_cigam' => '000123',
            'id_estado' => Estado::ID_CEARA,
            'razao_social' => 'RAZÃO EM MINÚSCULAS',
            'fantasia' => 'FANT / MIN',
            'cnpj_cpf' => '11222333000181',
        ]);
    }

    public function test_id_cigam_duplicado_falha_na_validacao(): void
    {
        Fornecedor::factory()->create(['id_cigam' => '000099']);

        $this->actingAs($this->userWithPermissions([Permissions::FORNECEDORES_CRIAR]))
            ->post(route('admin.fornecedores.store'), $this->fornecedorPayload([
                'id_cigam' => '99',
                'cnpj_cpf' => '52998224725',
                'id_estado' => Estado::ID_CEARA,
            ]))
            ->assertSessionHasErrors('id_cigam');
    }

    public function test_id_cigam_com_mais_de_seis_digitos_falha(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::FORNECEDORES_CRIAR]))
            ->post(route('admin.fornecedores.store'), $this->fornecedorPayload([
                'id_cigam' => '1234567',
                'cnpj_cpf' => '52998224725',
            ]))
            ->assertSessionHasErrors('id_cigam');
    }

    public function test_cnpj_cpf_tamanho_invalido_falha(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::FORNECEDORES_CRIAR]))
            ->post(route('admin.fornecedores.store'), $this->fornecedorPayload([
                'id_cigam' => '10',
                'cnpj_cpf' => '123',
            ]))
            ->assertSessionHasErrors('cnpj_cpf');
    }

    public function test_edicao_atualiza_registro(): void
    {
        $fornecedor = Fornecedor::factory()->create([
            'id_cigam' => '000050',
            'razao_social' => 'ANTES',
            'cnpj_cpf' => '52998224725',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::FORNECEDORES_EDITAR]))
            ->put(route('admin.fornecedores.update', $fornecedor), [
                'id_cigam' => '50',
                'id_estado' => Estado::ID_PERNAMBUCO,
                'razao_social' => 'Depois',
                'fantasia' => null,
                'cnpj_cpf' => '529.982.247-25',
            ])
            ->assertRedirect(route('admin.fornecedores.index'))
            ->assertSessionHas('success');

        $fornecedor->refresh();
        $this->assertSame('000050', $fornecedor->id_cigam);
        $this->assertSame('DEPOIS', $fornecedor->razao_social);
        $this->assertNull($fornecedor->fantasia);
        $this->assertSame(Estado::ID_PERNAMBUCO, (int) $fornecedor->id_estado);
        $this->assertSame('52998224725', $fornecedor->cnpj_cpf);
    }
}
