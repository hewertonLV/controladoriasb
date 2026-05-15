<?php

namespace Database\Seeders;

use App\Enums\Permissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeder idempotente de permissões.
 *
 * - Nunca apaga ou trunca registros.
 * - Usa `firstOrCreate` para preservar permissões adicionadas manualmente.
 * - Identidade: combinação única (name + guard_name).
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (Permissions::values() as $name) {
                Permission::firstOrCreate([
                    'name' => $name,
                    'guard_name' => 'web',
                ]);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
