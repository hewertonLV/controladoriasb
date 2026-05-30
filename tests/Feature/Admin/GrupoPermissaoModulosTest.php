<?php

namespace Tests\Feature\Admin;

use App\Enums\AppModulo;
use App\Enums\Permissions;
use App\Models\RoleAppModulo;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\CreatesUsersWithRoles;
use Tests\TestCase;

class GrupoPermissaoModulosTest extends TestCase
{
    use CreatesUsersWithRoles;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);
    }

    public function test_atualizar_grupo_sincroniza_modulos_do_hub(): void
    {
        $admin = $this->userWithPermissions([
            Permissions::GRUPOS_PERMISSOES_EDITAR,
        ]);

        $role = Role::findOrCreate('Operador Centralizador', 'web');

        $response = $this->actingAs($admin)->put(route('admin.grupos-permissoes.update', $role), [
            'name' => 'Operador Centralizador',
            'permissions' => [],
            'modulos' => [
                AppModulo::Centralizador->value,
            ],
        ]);

        $response->assertRedirect(route('admin.grupos-permissoes.index'));

        $this->assertDatabaseHas('role_app_modulos', [
            'role_id' => $role->id,
            'app_modulo' => AppModulo::Centralizador->value,
        ]);

        $this->assertSame(1, RoleAppModulo::query()->where('role_id', $role->id)->count());
    }

    public function test_tela_edicao_exibe_modulos_do_hub(): void
    {
        $admin = $this->userWithPermissions([
            Permissions::GRUPOS_PERMISSOES_EDITAR,
        ]);

        $role = Role::findOrCreate('Operador Centralizador', 'web');
        app(\App\Services\Modulos\RoleModuloService::class)->sincronizarModulos($role, [
            AppModulo::Centralizador,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.grupos-permissoes.edit', $role))
            ->assertOk()
            ->assertSee('Módulos do hub', false)
            ->assertSee('Centralizador', false)
            ->assertSee('name="modulos[]"', false);
    }
}
