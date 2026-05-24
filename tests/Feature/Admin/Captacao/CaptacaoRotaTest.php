<?php

namespace Tests\Feature\Admin\Captacao;

use App\Enums\Permissions;
use App\Models\Captacao\CaptacaoRota;

class CaptacaoRotaTest extends CaptacaoTestCase
{
    public function test_listagem_rotas_exige_permissao(): void
    {
        $c = $this->cenarioCaptacaoBasico();

        $this->actingAs($this->userWithPermissions([]))
            ->get(route('admin.captacao.rotas.index'))
            ->assertForbidden();

        $this->actingAs($this->userWithPermissions([Permissions::CAPTACAO_LOTE_VISUALIZAR]))
            ->get(route('admin.captacao.rotas.index'))
            ->assertOk()
            ->assertSee('Rota Teste', false)
            ->assertDontSee('Nova rota', false);
    }

    public function test_cadastro_e_edicao_de_rota(): void
    {
        $c = $this->cenarioCaptacaoBasico();
        $user = $this->captacaoManager();

        $this->actingAs($user)
            ->get(route('admin.captacao.rotas.index'))
            ->assertOk()
            ->assertSee('Nova rota', false);

        $this->actingAs($user)
            ->post(route('admin.captacao.rotas.store'), [
                'id_unidade_negocio_galpao' => $c['galpao']->id,
                'nome' => 'Rota Norte',
                'ativo' => '1',
            ])
            ->assertRedirect(route('admin.captacao.rotas.index', ['galpao' => $c['galpao']->id]));

        $nova = CaptacaoRota::query()->where('nome', 'Rota Norte')->first();
        $this->assertNotNull($nova);
        $this->assertTrue($nova->ativo);

        $this->actingAs($user)
            ->put(route('admin.captacao.rotas.update', $nova), [
                'id_unidade_negocio_galpao' => $c['galpao']->id,
                'nome' => 'Rota Norte Atualizada',
                'ativo' => '0',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('captacao_rotas', [
            'id' => $nova->id,
            'nome' => 'Rota Norte Atualizada',
            'ativo' => false,
        ]);
    }

    public function test_usuario_sem_editar_nao_cria_rota(): void
    {
        $c = $this->cenarioCaptacaoBasico();

        $this->actingAs($this->userWithPermissions([Permissions::CAPTACAO_LOTE_VISUALIZAR]))
            ->post(route('admin.captacao.rotas.store'), [
                'id_unidade_negocio_galpao' => $c['galpao']->id,
                'nome' => 'Rota Bloqueada',
            ])
            ->assertForbidden();
    }
}
