<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Roles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGrupoPermissaoRequest;
use App\Http\Requests\Admin\UpdateGrupoPermissaoRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    private const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    private const PER_PAGE_DEFAULT = 20;

    private const SORT_DEFAULT = 'name';

    private const DIRECTION_DEFAULT = 'asc';

    private const ALLOWED_SORTS = [
        'name' => 'name',
        'guard_name' => 'guard_name',
        'permissions_count' => 'permissions_count',
        'created_at' => 'created_at',
    ];

    public function index(Request $request): View
    {
        $filtros = $this->extrairFiltros($request);

        $query = Role::query()
            ->where('guard_name', self::GUARD)
            ->withCount('permissions');

        if ($filtros['search'] !== '') {
            $query->where('name', 'like', '%'.$filtros['search'].'%');
        }

        $query
            ->orderBy(self::ALLOWED_SORTS[$filtros['sort']] ?? self::ALLOWED_SORTS[self::SORT_DEFAULT], $filtros['direction'])
            ->orderBy('id');

        if ($filtros['per_page'] === 'all') {
            $total = (clone $query)->toBase()->count();
            $roles = $query->get();
            $exibindo = $roles->count();
        } else {
            $paginator = $query->paginate((int) $filtros['per_page'])->appends($filtros);
            $roles = $paginator;
            $total = $paginator->total();
            $exibindo = count((array) $paginator->items());
        }

        $payload = [
            'roles' => $roles,
            'roleProgramador' => Roles::PROGRAMADOR->value,
            'filtros' => $filtros,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'total' => $total,
            'exibindo' => $exibindo,
        ];

        if ($request->ajax()) {
            return view('admin.grupos-permissoes._table', $payload);
        }

        return view('admin.grupos-permissoes.index', $payload);
    }

    /**
     * @return array{search: string, per_page: int|string, sort: string, direction: string}
     */
    private function extrairFiltros(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));

        $perPageRaw = (string) $request->query('per_page', (string) self::PER_PAGE_DEFAULT);
        if ($perPageRaw === 'all') {
            $perPage = 'all';
        } else {
            $candidate = (int) $perPageRaw;
            $perPage = in_array($candidate, self::PER_PAGE_OPTIONS, true) ? $candidate : self::PER_PAGE_DEFAULT;
        }

        $sortRaw = (string) $request->query('sort', self::SORT_DEFAULT);
        $sort = array_key_exists($sortRaw, self::ALLOWED_SORTS) ? $sortRaw : self::SORT_DEFAULT;

        $directionRaw = mb_strtolower((string) $request->query('direction', self::DIRECTION_DEFAULT));
        $direction = in_array($directionRaw, ['asc', 'desc'], true) ? $directionRaw : self::DIRECTION_DEFAULT;

        return [
            'search' => $search,
            'per_page' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    public function create(): View
    {
        return view('admin.grupos-permissoes.create', [
            'role' => new Role(['guard_name' => self::GUARD]),
            'permissionGroups' => $this->groupPermissions($this->permissions()),
            'selectedPermissionIds' => collect(),
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
