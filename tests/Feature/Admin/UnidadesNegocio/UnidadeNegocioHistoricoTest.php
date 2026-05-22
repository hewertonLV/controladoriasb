<?php

namespace Tests\Feature\Admin\UnidadesNegocio;

use App\Enums\Permissions;
use App\Models\UnidadeNegocio;
use App\Models\UnidadeNegocioHistorico;
use App\Services\UnidadesNegocio\UnidadeNegocioAuditoriaService;

class UnidadeNegocioHistoricoTest extends UnidadeNegocioTestCase
{
    public function test_usuario_com_permissao_consegue_ver_historico(): void
    {
        $user = $this->userWithPermissions([Permissions::UNIDADES_NEGOCIO_HISTORICO]);
        $unidade = UnidadeNegocio::factory()->create();

        $auditoria = app(UnidadeNegocioAuditoriaService::class);
        $auditoria->registrarCriacao($unidade, $user, UnidadeNegocioHistorico::ORIGEM_MANUAL);
        $snap = $auditoria->snapshot($unidade);
        $auditoria->registrarAtualizacao(
            $unidade,
            $snap,
            array_replace($snap, ['nome' => 'Nome Atualizado']),
            $user,
            UnidadeNegocioHistorico::ORIGEM_MANUAL,
        );

        $this->actingAs($user)
            ->get(route('admin.unidades-negocio.historico', $unidade))
            ->assertOk()
            ->assertSee('Criação')
            ->assertSee('Atualização')
            ->assertSee('Manual')
            ->assertSee($user->name)
            ->assertSee('Nome Atualizado');
    }

    public function test_usuario_sem_permissao_recebe_403_no_historico(): void
    {
        $user = $this->userWithoutEmpresaPermissions();
        $unidade = UnidadeNegocio::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.unidades-negocio.historico', $unidade))
            ->assertForbidden();
    }
}
