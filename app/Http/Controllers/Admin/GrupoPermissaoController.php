<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Roles;
use App\Enums\AppModulo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGrupoPermissaoRequest;
use App\Http\Requests\Admin\UpdateGrupoPermissaoRequest;
use App\Services\Modulos\RoleModuloService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class GrupoPermissaoController extends Controller
{
    /**
     * Guard padrão do sistema.
     */
    private const GUARD = 'web';

    public function __construct(
        private readonly RoleModuloService $roleModulos,
    ) {}

    public function index(): View
    {
        $roles = Role::query()
            ->where('guard_name', self::GUARD)
            ->withCount('permissions')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return view('admin.grupos-permissoes.index', [
            'roles' => $roles,
            'roleProgramador' => Roles::PROGRAMADOR->value,
        ]);
    }

    public function create(): View
    {
        return view('admin.grupos-permissoes.create', [
            'role' => new Role(['guard_name' => self::GUARD]),
            'permissionGroups' => $this->groupPermissions($this->permissions()),
            'selectedPermissionIds' => collect(),
            'selectedModulos' => collect(),
            'modulosDisponiveis' => AppModulo::cases(),
            'isProgramador' => false,
        ]);
    }

    public function store(StoreGrupoPermissaoRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => self::GUARD,
        ]);

        $permissions = $this->resolvePermissions($data['permissions'] ?? []);
        $role->syncPermissions($permissions);
        $this->roleModulos->sincronizarModulos($role, $data['modulos'] ?? []);

        return redirect()
            ->route('admin.grupos-permissoes.index')
            ->with('success', "Grupo \"{$role->name}\" criado com sucesso.");
    }

    public function edit(Role $role): View
    {
        $isProgramador = $this->isProgramador($role);

        return view('admin.grupos-permissoes.edit', [
            'role' => $role,
            'permissionGroups' => $this->groupPermissions($this->permissions()),
            'selectedPermissionIds' => $role->permissions->pluck('id'),
            'selectedModulos' => $this->roleModulos->modulosDoRole($role)->map->value,
            'modulosDisponiveis' => AppModulo::cases(),
            'isProgramador' => $isProgramador,
        ]);
    }

    public function update(UpdateGrupoPermissaoRequest $request, Role $role): RedirectResponse
    {
        if ($this->isProgramador($role)) {
            return redirect()
                ->route('admin.grupos-permissoes.edit', $role)
                ->with('error', 'O grupo Programador não pode ser alterado. Seu acesso total é garantido pelo sistema.');
        }

        $data = $request->validated();

        $role->update(['name' => $data['name']]);

        $permissions = $this->resolvePermissions($data['permissions'] ?? []);
        $role->syncPermissions($permissions);
        $this->roleModulos->sincronizarModulos($role, $data['modulos'] ?? []);

        return redirect()
            ->route('admin.grupos-permissoes.index')
            ->with('success', "Grupo \"{$role->name}\" atualizado com sucesso.");
    }

    /**
     * Carrega todas as permissões do guard.
     *
     * @return Collection<int, Permission>
     */
    private function permissions(): Collection
    {
        return Permission::query()
            ->where('guard_name', self::GUARD)
            ->orderBy('name')
            ->get();
    }

    /**
     * Agrupa permissões pelo prefixo antes do ponto.
     *
     * Ex.: "usuarios.criar" => grupo "Usuários".
     *
     * @param  Collection<int, Permission>  $permissions
     * @return array<string, list<array{id:int,name:string,action:string}>>
     */
    private function groupPermissions(Collection $permissions): array
    {
        return $permissions
            ->groupBy(function (Permission $permission): string {
                $segments = explode('.', $permission->name, 2);

                return $this->humanize($segments[0] ?? 'Geral');
            })
            ->map(function (Collection $items): array {
                return $items
                    ->map(function (Permission $permission): array {
                        $segments = explode('.', $permission->name, 2);

                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'action' => $this->humanize($segments[1] ?? $permission->name),
                        ];
                    })
                    ->values()
                    ->all();
            })
            ->sortKeys()
            ->all();
    }

    /**
     * Resolve apenas IDs de permissões realmente existentes no guard.
     *
     * @param  array<int, int|string>  $ids
     * @return Collection<int, Permission>
     */
    private function resolvePermissions(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Permission::query()
            ->where('guard_name', self::GUARD)
            ->whereIn('id', array_map('intval', $ids))
            ->get();
    }

    private function isProgramador(Role $role): bool
    {
        return $role->name === Roles::PROGRAMADOR->value;
    }

    /**
     * "grupos-permissoes" => "Grupos Permissoes"
     */
    private function humanize(string $value): string
    {
        $value = str_replace(['-', '_', '.'], ' ', $value);

        return mb_convert_case(trim($value), MB_CASE_TITLE, 'UTF-8');
    }
}
