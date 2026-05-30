<?php

namespace App\Services\Modulos;

use App\Enums\AppModulo;
use App\Models\RoleAppModulo;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

class RoleModuloService
{
    /**
     * @return Collection<int, AppModulo>
     */
    public function modulosDoRole(Role $role): Collection
    {
        return RoleAppModulo::query()
            ->where('role_id', $role->id)
            ->orderBy('app_modulo')
            ->pluck('app_modulo')
            ->map(fn (string $valor) => AppModulo::from($valor))
            ->values();
    }

    /**
     * @return Collection<int, AppModulo>
     */
    public function modulosDoUsuario(User $user): Collection
    {
        $roleIds = $user->roles()->pluck('id');

        if ($roleIds->isEmpty()) {
            return collect();
        }

        return RoleAppModulo::query()
            ->whereIn('role_id', $roleIds)
            ->orderBy('app_modulo')
            ->pluck('app_modulo')
            ->unique()
            ->map(fn (string $valor) => AppModulo::from($valor))
            ->sortBy(fn (AppModulo $modulo) => $this->ordemModulo($modulo))
            ->values();
    }

    /**
     * @param  list<string|AppModulo>  $modulos
     */
    public function sincronizarModulos(Role $role, array $modulos): void
    {
        $valores = collect($modulos)
            ->map(function (string|AppModulo $modulo): string {
                if ($modulo instanceof AppModulo) {
                    return $modulo->value;
                }

                return AppModulo::from($modulo)->value;
            })
            ->unique()
            ->values();

        RoleAppModulo::query()
            ->where('role_id', $role->id)
            ->whereNotIn('app_modulo', $valores)
            ->delete();

        foreach ($valores as $valor) {
            RoleAppModulo::query()->firstOrCreate([
                'role_id' => $role->id,
                'app_modulo' => $valor,
            ]);
        }
    }

    private function ordemModulo(AppModulo $modulo): int
    {
        $indice = array_search($modulo, AppModulo::cases(), true);

        return $indice === false ? PHP_INT_MAX : $indice;
    }
}
