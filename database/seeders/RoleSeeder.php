<?php

namespace Database\Seeders;

use App\Enums\Roles;
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
}
