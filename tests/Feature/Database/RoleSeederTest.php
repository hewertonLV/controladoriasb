<?php

namespace Tests\Feature\Database;

use App\Enums\Permissions;
use App\Enums\Roles;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_cria_grupo_vendedor_com_permissoes_padrao(): void
    {
        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
        ]);

        $vendedor = Role::query()
            ->where('name', Roles::VENDEDOR->value)
            ->where('guard_name', 'web')
            ->first();

        $this->assertNotNull($vendedor);

        foreach (Permissions::permissoesGrupoVendedor() as $permission) {
            $this->assertTrue(
                $vendedor->hasPermissionTo($permission),
                "Grupo Vendedor deveria ter a permissão {$permission}.",
            );
        }

        $this->assertDatabaseHas('role_app_modulos', [
            'role_id' => $vendedor->id,
            'app_modulo' => 'captacao',
        ]);

        $this->assertDatabaseMissing('role_app_modulos', [
            'role_id' => $vendedor->id,
            'app_modulo' => 'centralizador',
        ]);

        $this->assertFalse($vendedor->hasPermissionTo(Permissions::EMPRESAS_VISUALIZAR));
        $this->assertFalse($vendedor->hasPermissionTo(Permissions::MOVIMENTACOES_TRANSFERENCIAS_IMPORTAR));
    }
}
