<?php

namespace Tests\Feature\Admin\Clientes;

use App\Enums\Permissions;
use App\Models\Cliente;

class ClienteTest extends ClienteTestCase
{
    public function test_convidado_e_redirecionado_para_login_na_listagem(): void
    {
        $this->get(route('admin.clientes.index'))
            ->assertRedirect(route('login'));
    }

    public function test_usuario_sem_permissao_recebe_403_na_listagem(): void
    {
        $this->actingAs($this->userWithoutEmpresaPermissions())
            ->get(route('admin.clientes.index'))
            ->assertForbidden();
    }

    public function test_usuario_com_visualizar_acessa_listagem(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::CLIENTES_VISUALIZAR]))
            ->get(route('admin.clientes.index'))
            ->assertOk();
    }

    public function test_listagem_ajax_retorna_partial_da_tabela(): void
    {
        Cliente::factory()->create(['razao_social' => 'CLIENTE AJAX']);

        $this->actingAs($this->clientesManager())
            ->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'text/html',
            ])
            ->get(route('admin.clientes.index'))
            ->assertOk()
            ->assertSee('CLIENTE AJAX', false);
    }

    public function test_cadastro_com_sucesso_normaliza_campos(): void
    {
        $payload = $this->clientePayload([
            'id_cigam' => '12.3',
            'razao_social' => 'raZao em MiNusCuLa',
            'cnpj_cpf' => '11.222.333/0001-81',
            'desconto_nf' => '10.5',
            'desconto_contrato' => '0',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::CLIENTES_CRIAR]))
            ->post(route('admin.clientes.store'), $payload)
            ->assertStatus(302)
            ->assertSessionHas('success');

        $this->assertDatabaseHas('clientes', [
            'id_cigam' => '000123',
            'razao_social' => 'RAZAO EM MINUSCULA',
            'cnpj_cpf' => '11222333000181',
            'desconto_nf' => 10.5,
            'desconto_contrato' => 0,
        ]);
    }

    public function test_desconto_negativo_falha_validacao(): void
    {
        $payload = $this->clientePayload([
            'desconto_nf' => '-1',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::CLIENTES_CRIAR]))
            ->post(route('admin.clientes.store'), $payload)
            ->assertSessionHasErrors('desconto_nf');
    }

    public function test_id_cigam_duplicado_falha_validacao(): void
    {
        $existing = Cliente::factory()->create(['id_cigam' => '000099']);

        $this->actingAs($this->userWithPermissions([Permissions::CLIENTES_CRIAR]))
            ->post(route('admin.clientes.store'), $this->clientePayload([
                'id_cigam' => '99',
                'cnpj_cpf' => $existing->cnpj_cpf,
            ]))
            ->assertSessionHasErrors('id_cigam');
    }

    public function test_edicao_atualiza_registro(): void
    {
        $cliente = Cliente::factory()->create([
            'id_cigam' => '000050',
            'razao_social' => 'ANTES',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::CLIENTES_EDITAR]))
            ->put(route('admin.clientes.update', $cliente), [
                'id_cigam' => '50',
                'razao_social' => 'DEPOIS',
                'cnpj_cpf' => $cliente->cnpj_cpf,
                'id_unidade_negocio' => $cliente->id_unidade_negocio,
                'id_praca' => $cliente->id_praca,
                'grupo_id' => $cliente->grupo_id,
                'desconto_nf' => '1.25',
                'desconto_contrato' => '2.50',
            ])
            ->assertStatus(302)
            ->assertSessionHas('success');

        $this->assertSame('DEPOIS', $cliente->fresh()->razao_social);
    }
}
