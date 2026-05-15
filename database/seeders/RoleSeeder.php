<?php

namespace Database\Seeders;

use App\Enums\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder idempotente de roles (grupos/perfis).
 *
 * - Nunca apaga ou trunca registros.
 * - Usa `firstOrCreate` para preservar roles renomeados manualmente.
 * - Identidade: combinação única (name + guard_name).
 * - NÃO sincroniza permissões aqui: o vínculo entre roles e permissões
 *   é gerido manualmente na aplicação (telas administrativas).
 *   O acesso total do Programador é garantido por Gate::before.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (Roles::values() as $name) {
                Role::firstOrCreate([
                    'name' => $name,
                    'guard_name' => 'web',
                ]);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
