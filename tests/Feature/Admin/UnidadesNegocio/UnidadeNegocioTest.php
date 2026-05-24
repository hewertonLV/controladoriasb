<?php

namespace Tests\Feature\Admin\UnidadesNegocio;

use App\Enums\Permissions;
use App\Models\Estado;
use App\Models\UnidadeNegocio;

class UnidadeNegocioTest extends UnidadeNegocioTestCase
{
    public function test_convidado_e_redirecionado_para_login_na_listagem(): void
    {
        $this->get(route('admin.unidades-negocio.index'))
            ->assertRedirect(route('login'));
    }

    public function test_usuario_sem_permissao_recebe_403_na_listagem(): void
    {
        $this->actingAs($this->userWithoutEmpresaPermissions())
            ->get(route('admin.unidades-negocio.index'))
            ->assertForbidden();
    }

    public function test_usuario_com_visualizar_acessa_listagem(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::UNIDADES_NEGOCIO_VISUALIZAR]))
            ->get(route('admin.unidades-negocio.index'))
            ->assertOk();
    }

    public function test_listagem_usa_datatable_com_registros(): void
    {
        UnidadeNegocio::factory()->create(['nome' => 'UNIDADE DATATABLE']);

        $this->actingAs($this->unidadesNegocioManager())
            ->get(route('admin.unidades-negocio.index'))
            ->assertOk()
            ->assertSee('UNIDADE DATATABLE', false)
            ->assertSee('id="unidades-negocio-datatable"', false)
            ->assertSee('data-admin-datatable', false)
            ->assertSee('id="adminConfirmModal"', false)
            ->assertSee('data-confirm-variant="danger"', false);
    }

    public function test_cadastro_com_sucesso_normaliza_campos(): void
    {
        $payload = $this->unidadePayload([
            'id_cigam' => '77.001',
            'razao_social' => 'razão social teste',
            'nome' => 'nome teste',
            'cpf_cnpj' => '11.222.333/0001-81',
            'custo_operacional' => '99.9',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::UNIDADES_NEGOCIO_CRIAR]))
            ->post(route('admin.unidades-negocio.store'), $payload)
            ->assertRedirect(route('admin.unidades-negocio.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('unidades_negocio', [
            'id_cigam' => '077001',
            'razao_social' => 'RAZÃO SOCIAL TESTE',
            'nome' => 'NOME TESTE',
            'cpf_cnpj' => '11222333000181',
            'custo_operacional' => '99.90',
            'id_estado' => Estado::ID_CEARA,
            'status' => true,
            'possui_estoque' => false,
        ]);
    }

    public function test_cadastro_sem_cpf_cnpj_grava_null(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::UNIDADES_NEGOCIO_CRIAR]))
            ->post(route('admin.unidades-negocio.store'), $this->unidadePayload([
                'id_cigam' => '77002',
                'cpf_cnpj' => '',
            ]))
            ->assertRedirect(route('admin.unidades-negocio.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('unidades_negocio', [
            'id_cigam' => '077002',
            'cpf_cnpj' => null,
        ]);
    }

    public function test_cadastro_com_cpf_cnpj_null_grava_null(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::UNIDADES_NEGOCIO_CRIAR]))
            ->post(route('admin.unidades-negocio.store'), $this->unidadePayload([
                'id_cigam' => '77003',
                'cpf_cnpj' => null,
            ]))
            ->assertRedirect(route('admin.unidades-negocio.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('unidades_negocio', [
            'id_cigam' => '077003',
            'cpf_cnpj' => null,
        ]);
    }

    public function test_duas_unidades_podem_ter_mesmo_cpf_cnpj(): void
    {
        UnidadeNegocio::factory()->create([
            'id_cigam' => '077004',
            'cpf_cnpj' => '11222333000181',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::UNIDADES_NEGOCIO_CRIAR]))
            ->post(route('admin.unidades-negocio.store'), $this->unidadePayload([
                'id_cigam' => '77005',
                'cpf_cnpj' => '11.222.333/0001-81',
            ]))
            ->assertRedirect(route('admin.unidades-negocio.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, UnidadeNegocio::query()
            ->where('cpf_cnpj', '11222333000181')
            ->count());
    }

    public function test_id_cigam_duplicado_falha_na_validacao(): void
    {
        UnidadeNegocio::factory()->create(['id_cigam' => '088001']);

        $this->actingAs($this->userWithPermissions([Permissions::UNIDADES_NEGOCIO_CRIAR]))
            ->post(route('admin.unidades-negocio.store'), $this->unidadePayload(['id_cigam' => '88001']))
            ->assertSessionHasErrors('id_cigam');
    }

    public function test_custo_operacional_negativo_e_normalizado_para_valor_positivo(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::UNIDADES_NEGOCIO_CRIAR]))
            ->post(route('admin.unidades-negocio.store'), $this->unidadePayload([
                'custo_operacional' => '-1',
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('unidades_negocio', [
            'id_cigam' => '009001',
            'custo_operacional' => '1.00',
        ]);
    }

    public function test_edicao_atualiza_registro(): void
    {
        $unidade = UnidadeNegocio::factory()->create([
            'id_cigam' => '099001',
            'nome' => 'ANTES',
            'razao_social' => 'ANTES',
            'custo_operacional' => '5.00',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::UNIDADES_NEGOCIO_EDITAR]))
            ->put(route('admin.unidades-negocio.update', $unidade), $this->unidadePayload([
                'id_cigam' => '99001',
                'nome' => 'Depois',
                'razao_social' => 'Depois Razão',
                'custo_operacional' => '7.25',
            ]))
            ->assertRedirect(route('admin.unidades-negocio.index'))
            ->assertSessionHas('success');

        $unidade->refresh();
        $this->assertSame('DEPOIS', $unidade->nome);
        $this->assertSame('DEPOIS RAZÃO', $unidade->razao_social);
        $this->assertSame('7.25', $unidade->custo_operacional);
    }

    public function test_inativar_altera_status(): void
    {
        $unidade = UnidadeNegocio::factory()->create(['status' => true]);

        $this->actingAs($this->userWithPermissions([Permissions::UNIDADES_NEGOCIO_INATIVAR]))
            ->post(route('admin.unidades-negocio.inativar', $unidade))
            ->assertRedirect(route('admin.unidades-negocio.index'))
            ->assertSessionHas('success', 'Unidade de Negócio inativada com sucesso.');

        $this->assertFalse($unidade->fresh()->status);
    }

    public function test_inativar_idempotente_retorna_info(): void
    {
        $unidade = UnidadeNegocio::factory()->inativa()->create();

        $this->actingAs($this->userWithPermissions([Permissions::UNIDADES_NEGOCIO_INATIVAR]))
            ->post(route('admin.unidades-negocio.inativar', $unidade))
            ->assertRedirect(route('admin.unidades-negocio.index'))
            ->assertSessionHas('info');
    }

    public function test_ativar_restaura_status(): void
    {
        $unidade = UnidadeNegocio::factory()->inativa()->create();

        $this->actingAs($this->userWithPermissions([Permissions::UNIDADES_NEGOCIO_ATIVAR]))
            ->post(route('admin.unidades-negocio.ativar', $unidade))
            ->assertRedirect(route('admin.unidades-negocio.index'))
            ->assertSessionHas('success', 'Unidade de Negócio ativada com sucesso.');

        $this->assertTrue($unidade->fresh()->status);
    }

    public function test_ativar_idempotente_retorna_info(): void
    {
        $unidade = UnidadeNegocio::factory()->create(['status' => true]);

        $this->actingAs($this->userWithPermissions([Permissions::UNIDADES_NEGOCIO_ATIVAR]))
            ->post(route('admin.unidades-negocio.ativar', $unidade))
            ->assertRedirect(route('admin.unidades-negocio.index'))
            ->assertSessionHas('info');
    }

    public function test_programador_executa_fluxo_completo(): void
    {
        $programador = $this->programadorUser();

        $this->actingAs($programador)
            ->get(route('admin.unidades-negocio.index'))
            ->assertOk();

        $this->actingAs($programador)
            ->post(route('admin.unidades-negocio.store'), $this->unidadePayload(['id_cigam' => '66001']))
            ->assertRedirect(route('admin.unidades-negocio.index'));

        $unidade = UnidadeNegocio::query()->where('id_cigam', '066001')->firstOrFail();

        $this->actingAs($programador)
            ->put(route('admin.unidades-negocio.update', $unidade), $this->unidadePayload([
                'id_cigam' => '66001',
                'nome' => 'Atualizado pelo programador',
                'razao_social' => 'Atualizado pelo programador',
            ]))
            ->assertRedirect(route('admin.unidades-negocio.index'));

        $this->actingAs($programador)
            ->post(route('admin.unidades-negocio.inativar', $unidade->fresh()))
            ->assertRedirect(route('admin.unidades-negocio.index'));

        $this->actingAs($programador)
            ->post(route('admin.unidades-negocio.ativar', $unidade->fresh()))
            ->assertRedirect(route('admin.unidades-negocio.index'));

        $this->assertTrue($unidade->fresh()->status);
    }

    public function test_galpao_operacional_pode_emitir_nota_fiscal(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::UNIDADES_NEGOCIO_CRIAR]))
            ->post(route('admin.unidades-negocio.store'), $this->unidadePayload([
                'id_cigam' => '88001',
                'nome' => 'CD BARBALHA',
                'razao_social' => 'CD BARBALHA',
                'possui_estoque' => true,
                'is_galpao_operacional' => true,
                'emite_nota_fiscal' => true,
            ]))
            ->assertRedirect(route('admin.unidades-negocio.index'));

        $this->assertDatabaseHas('unidades_negocio', [
            'id_cigam' => '088001',
            'is_galpao_operacional' => true,
            'emite_nota_fiscal' => true,
        ]);
    }
}
