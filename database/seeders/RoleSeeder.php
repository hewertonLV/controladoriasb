<?php

namespace Database\Seeders;

use App\Enums\AppModulo;
use App\Enums\Permissions;
use App\Enums\Roles;
use App\Services\Modulos\RoleModuloService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder idempotente de roles (grupos/perfis).
 *
 * - Nunca apaga ou trunca registros.
 * - Usa `firstOrCreate` para preservar roles renomeados manualmente.
 * - Administrador recebe permissões novas via givePermissionTo (não remove as já configuradas).
 * - Programador: acesso total via Gate::before (sem vínculo individual obrigatório).
 */
class RoleSeeder extends Seeder
{
    private const GUARD = 'web';

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (Roles::values() as $name) {
                Role::firstOrCreate([
                    'name' => $name,
                    'guard_name' => self::GUARD,
                ]);
            }

            $this->atribuirPermissoesNovasAoAdministrador();
            $this->atribuirPermissoesPadraoVendedor();
            $this->atribuirModulosPadrao();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function atribuirPermissoesNovasAoAdministrador(): void
    {
        $admin = Role::query()
            ->where('name', Roles::ADMINISTRADOR->value)
            ->where('guard_name', self::GUARD)
            ->first();

        if ($admin === null) {
            return;
        }

        Permission::query()
            ->where('guard_name', self::GUARD)
            ->each(function (Permission $permission) use ($admin): void {
                if (! $admin->hasPermissionTo($permission)) {
                    $admin->givePermissionTo($permission);
                }
            });
    }

    private function atribuirPermissoesPadraoVendedor(): void
    {
        $vendedor = Role::query()
            ->where('name', Roles::VENDEDOR->value)
            ->where('guard_name', self::GUARD)
            ->first();

        if ($vendedor === null) {
            return;
        }

        foreach (Permissions::permissoesGrupoVendedor() as $permissionName) {
            $permission = Permission::query()
                ->where('guard_name', self::GUARD)
                ->where('name', $permissionName)
                ->first();

            if ($permission !== null && ! $vendedor->hasPermissionTo($permission)) {
                $vendedor->givePermissionTo($permission);
            }
        }
    }

    private function atribuirModulosPadrao(): void
    {
        $modulos = app(RoleModuloService::class);

        $todosModulos = AppModulo::cases();

        $mapa = [
            Roles::ADMINISTRADOR->value => $todosModulos,
            Roles::PROGRAMADOR->value => $todosModulos,
            Roles::CONTROLADORIA->value => [AppModulo::Administrador],
            Roles::VENDEDOR->value => [
                AppModulo::Captacao,
                AppModulo::Transferencia,
                AppModulo::Venda,
            ],
        ];

        foreach ($mapa as $nomeRole => $modulosDoGrupo) {
            $role = Role::query()
                ->where('name', $nomeRole)
                ->where('guard_name', self::GUARD)
                ->first();

            if ($role === null) {
                continue;
            }

            $modulos->sincronizarModulos($role, $modulosDoGrupo);
        }
    }
}
