<?php

namespace Tests\Feature\Admin\Empresas;

use App\Enums\Permissions;
use App\Models\Empresa;
use Illuminate\Support\Facades\Route;

class EmpresaPermissaoTest extends EmpresasTestCase
{
    public function test_usuario_sem_permissao_nao_acessa_listagem(): void
    {
        $this->actingAs($this->userWithoutEmpresaPermissions())
            ->get(route('admin.empresas.index'))
            ->assertForbidden();
    }

    public function test_usuario_com_empresas_visualizar_acessa_listagem(): void
    {
        $this->actingAs($this->userWithPermissions([Permissions::EMPRESAS_VISUALIZAR]))
            ->get(route('admin.empresas.index'))
            ->assertOk();
    }

    public function test_rotas_de_mutacao_de_cadastro_foram_removidas(): void
    {
        foreach ([
            'admin.empresas.create',
            'admin.empresas.store',
            'admin.empresas.edit',
            'admin.empresas.update',
            'admin.empresas.inativar',
            'admin.empresas.reativar',
        ] as $name) {
            $this->assertFalse(Route::has($name), "A rota {$name} não deveria existir.");
        }
    }

    public function test_programador_acessa_listagem_e_historico(): void
    {
        $programador = $this->programadorUser();
        $empresa = Empresa::factory()->create();

        $this->actingAs($programador)->get(route('admin.empresas.index'))->assertOk();
        $this->actingAs($programador)->get(route('admin.empresas.historico', $empresa))->assertOk();
    }
}
