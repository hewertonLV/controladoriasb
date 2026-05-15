<?php

namespace Tests\Feature\Admin\Empresas;

use App\Enums\Permissions;
use App\Models\Empresa;
use App\Models\EmpresaHistorico;
use App\Services\Empresas\EmpresaAuditoriaService;

class EmpresaHistoricoTest extends EmpresasTestCase
{
    public function test_usuario_com_permissao_consegue_ver_historico_com_criacao_e_atualizacao(): void
    {
        $user = $this->userWithPermissions([Permissions::EMPRESAS_HISTORICO]);
        $empresa = Empresa::factory()->create();

        $auditoria = app(EmpresaAuditoriaService::class);
        $auditoria->registrarCriacao($empresa, $user, EmpresaHistorico::ORIGEM_MANUAL);
        $snap = $auditoria->snapshot($empresa);
        $auditoria->registrarAtualizacao(
            $empresa,
            $snap,
            array_replace($snap, ['nome_exibicao' => 'Nome Atualizado']),
            $user,
            EmpresaHistorico::ORIGEM_MANUAL,
        );

        $this->actingAs($user)
            ->get(route('admin.empresas.historico', $empresa))
            ->assertOk()
            ->assertSee('Criação')
            ->assertSee('Atualização')
            ->assertSee('Manual')
            ->assertSee($user->name)
            ->assertSee('Nome Atualizado');
    }

    public function test_historico_registra_usuario_origem_e_campos_alterados(): void
    {
        $user = $this->userWithPermissions([Permissions::EMPRESAS_HISTORICO]);
        $empresa = Empresa::factory()->create();

        $auditoria = app(EmpresaAuditoriaService::class);
        $antes = $auditoria->snapshot($empresa);
        $depois = array_replace($antes, ['nome_exibicao' => 'Depois']);

        $auditoria->registrarAtualizacao(
            $empresa,
            $antes,
            $depois,
            $user,
            EmpresaHistorico::ORIGEM_MANUAL,
        );

        $historico = EmpresaHistorico::query()
            ->where('empresa_id', $empresa->id)
            ->where('acao', EmpresaHistorico::ACAO_ATUALIZACAO)
            ->firstOrFail();

        $this->assertSame($user->id, $historico->user_id);
        $this->assertSame(EmpresaHistorico::ORIGEM_MANUAL, $historico->origem);
        $this->assertContains('nome_exibicao', collect($historico->alteracoes)->pluck('campo')->all());
    }

    public function test_usuario_sem_permissao_recebe_403_no_historico(): void
    {
        $user = $this->userWithoutEmpresaPermissions();
        $empresa = Empresa::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.empresas.historico', $empresa))
            ->assertForbidden();
    }
}
