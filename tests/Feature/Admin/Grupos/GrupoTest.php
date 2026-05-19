<?php

namespace Tests\Feature\Admin\Grupos;

use App\Enums\Permissions;
use App\Models\Grupo;

class GrupoTest extends GrupoTestCase
{
    public function test_convidado_e_redirecionado_para_login_na_listagem(): void
    {
        $this->get(route('admin.grupos.index'))
            ->assertRedirect(route('login'));
    }

    public function test_usuario_sem_permissao_recebe_403_na_listagem(): void
    {
        $this->actingAs($this->userWithoutEmpresaPermissions())
            ->get(route('admin.grupos.index'))
            ->assertForbidden();
    }

    public function test_usuario_com_visualizar_acessa_listagem(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::GRUPOS_VISUALIZAR]))
            ->get(route('admin.grupos.index'))
            ->assertOk();
    }

    public function test_listagem_usa_datatable_com_registros(): void
    {
        Grupo::factory()->create(['nome' => 'GRUPO DATATABLE']);

        $this->actingAs($this->gruposManager())
            ->get(route('admin.grupos.index'))
            ->assertOk()
            ->assertSee('GRUPO DATATABLE', false)
            ->assertSee('id="grupos-datatable"', false)
            ->assertSee('data-admin-datatable', false);
    }

    public function test_cadastro_com_sucesso_normaliza_nome(): void
    {
        $payload = $this->grupoPayload([
            'nome' => '  grupo em minúsculas  ',
        ]);

        $this->actingAs($this->userWithPermissions([Permissions::GRUPOS_CRIAR]))
            ->post(route('admin.grupos.store'), $payload)
            ->assertRedirect(route('admin.grupos.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('grupos', [
            'nome' => 'GRUPO EM MINÚSCULAS',
        ]);
    }

    public function test_nome_duplicado_falha_na_validacao(): void
    {
        Grupo::factory()->create(['nome' => 'GRUPO DUPLICADO']);

        $this->actingAs($this->userWithPermissions([Permissions::GRUPOS_CRIAR]))
            ->post(route('admin.grupos.store'), $this->grupoPayload([
                'nome' => 'grupo duplicado',
            ]))
            ->assertSessionHasErrors('nome');
    }

    public function test_edicao_atualiza_registro(): void
    {
        $grupo = Grupo::factory()->create(['nome' => 'ANTES']);

        $this->actingAs($this->userWithPermissions([Permissions::GRUPOS_EDITAR]))
            ->put(route('admin.grupos.update', $grupo), [
                'nome' => 'Depois',
            ])
            ->assertRedirect(route('admin.grupos.index'))
            ->assertSessionHas('success');

        $grupo->refresh();
        $this->assertSame('DEPOIS', $grupo->nome);
    }
}
