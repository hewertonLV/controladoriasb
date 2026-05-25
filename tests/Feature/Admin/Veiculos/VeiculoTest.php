<?php

namespace Tests\Feature\Admin\Veiculos;

use App\Enums\Permissions;
use App\Models\UnidadeNegocio;
use App\Models\Veiculo;

class VeiculoTest extends VeiculoTestCase
{
    public function test_convidado_e_redirecionado_para_login_na_listagem(): void
    {
        $this->get(route('admin.veiculos.index'))
            ->assertRedirect(route('login'));
    }

    public function test_usuario_sem_permissao_recebe_403_na_listagem(): void
    {
        $this->actingAs($this->userWithoutEmpresaPermissions())
            ->get(route('admin.veiculos.index'))
            ->assertForbidden();
    }

    public function test_usuario_com_visualizar_acessa_listagem(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::VEICULOS_VISUALIZAR]))
            ->get(route('admin.veiculos.index'))
            ->assertOk();
    }

    public function test_listagem_usa_datatable_com_registros(): void
    {
        Veiculo::factory()->create(['nome' => 'VEICULO DATATABLE']);

        $this->actingAs($this->veiculosManager())
            ->get(route('admin.veiculos.index'))
            ->assertOk()
            ->assertSee('VEICULO DATATABLE', false)
            ->assertSee('id="veiculos-datatable"', false)
            ->assertSee('data-admin-datatable', false);
    }

    public function test_cadastro_com_sucesso_normaliza_campos(): void
    {
        $payload = $this->veiculoPayload([
            'id_sbs' => '12.3-4',
            'nome' => 'veICuLo teste',
            'tipo' => 'cARrO',
            'status' => 'inATIVO',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::VEICULOS_CRIAR]))
            ->post(route('admin.veiculos.store'), $payload)
            ->assertRedirect(route('admin.veiculos.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('veiculos', [
            'id_sbs' => 1234,
            'nome' => 'VEICULO TESTE',
            'tipo' => 'CARRO',
            'status' => 'INATIVO',
        ]);
    }

    public function test_cadastro_com_status_invalido_falha_validacao(): void
    {
        $payload = $this->veiculoPayload([
            'status' => 'PENDENTE',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::VEICULOS_CRIAR]))
            ->post(route('admin.veiculos.store'), $payload)
            ->assertSessionHasErrors('status');
    }

    public function test_formulario_criacao_exibe_select_de_unidades(): void
    {
        $unidade = UnidadeNegocio::factory()->create([
            'nome' => 'UNIDADE VEICULO TESTE',
            'id_cigam' => '000123',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::VEICULOS_CRIAR]))
            ->get(route('admin.veiculos.create'))
            ->assertOk()
            ->assertSee('id="id_unidade_negocio"', false)
            ->assertSee('UNIDADE VEICULO TESTE (000123)', false)
            ->assertDontSee('placeholder="Ex.: 1"', false);
    }

    public function test_edicao_atualiza_registro(): void
    {
        $veiculo = Veiculo::factory()->create([
            'id_sbs' => 9991,
            'nome' => 'ANTES',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::VEICULOS_EDITAR]))
            ->put(route('admin.veiculos.update', $veiculo), [
                'id_sbs' => 9991,
                'nome' => 'DEPOIS',
                'tipo' => 'CAMINHÃO',
                'id_unidade_negocio' => $veiculo->id_unidade_negocio,
                'status' => 'ATIVO',
            ])
            ->assertRedirect(route('admin.veiculos.index'))
            ->assertSessionHas('success');

        $this->assertSame('DEPOIS', $veiculo->fresh()->nome);
    }
}
