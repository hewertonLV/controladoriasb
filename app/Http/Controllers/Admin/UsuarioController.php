<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Roles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUsuarioRequest;
use App\Http\Requests\Admin\UpdateUsuarioRequest;
use App\Models\UnidadeNegocio;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    /**
     * Senha padrão atribuída na criação e no reset administrativo.
     * O usuário será forçado a trocá-la no próximo login.
     */
    public const DEFAULT_PASSWORD = 'sitiosbs';

    /**
     * E-mail do usuário base do sistema (Programador). Não pode ser
     * editado removendo a role Programador nem ter senha resetada.
     */
    private const PROTECTED_EMAIL = 'hewerton@sitiobarreiras.com.br';

    private const GUARD = 'web';

    private const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    private const PER_PAGE_DEFAULT = 20;

    private const SORT_DEFAULT = 'name';

    private const DIRECTION_DEFAULT = 'asc';

    private const ALLOWED_SORTS = [
        'name' => 'name',
        'login' => 'login',
        'email' => 'email',
        'ativo' => 'ativo',
        'must_change_password' => 'must_change_password',
        'created_at' => 'created_at',
    ];

    public function index(Request $request): View
    {
        $filtros = $this->extrairFiltros($request);
        $query = $this->aplicarFiltros(User::query()->with(['roles', 'unidadesNegocio:id,nome']), $filtros);

        if ($filtros['per_page'] === 'all') {
            $total = (clone $query)->toBase()->count();
            $users = $query->get();
            $exibindo = $users->count();
        } else {
            $paginator = $query->paginate((int) $filtros['per_page'])->appends($filtros);
            $users = $paginator;
            $total = $paginator->total();
            $exibindo = count((array) $paginator->items());
        }

        $payload = [
            'users' => $users,
            'protectedEmail' => self::PROTECTED_EMAIL,
            'filtros' => $filtros,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'total' => $total,
            'exibindo' => $exibindo,
        ];

        if ($request->ajax()) {
            return view('admin.usuarios._table', $payload);
        }

        return view('admin.usuarios.index', $payload);
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

    /**
     * @param  Builder<User>  $query
     * @param  array{search:string, per_page:int|string, sort:string, direction:string}  $filtros
     * @return Builder<User>
     */
    private function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        if ($filtros['search'] !== '') {
            $search = $filtros['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('login', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('roles', function (Builder $rq) use ($search) {
                        $rq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $query
            ->orderBy(self::ALLOWED_SORTS[$filtros['sort']] ?? self::ALLOWED_SORTS[self::SORT_DEFAULT], $filtros['direction'])
            ->orderBy('id');

        return $query;
    }

    public function create(): View
    {
        return view('admin.usuarios.create', [
            'user' => new User,
            'roles' => $this->roles(),
            'selectedRoleIds' => collect(),
            'unidadesNegocio' => $this->unidadesNegocio(),
            'selectedUnidadeNegocioIds' => collect(),
            'isProtected' => false,
        ]);
    }

    public function store(StoreUsuarioRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'login' => $data['login'],
            'email' => $data['email'],
            'password' => Hash::make(self::DEFAULT_PASSWORD),
            'must_change_password' => true,
            'ativo' => true,
            'email_verified_at' => now(),
        ]);

        $user->syncRoles($this->resolveRoles($data['roles'] ?? []));
        $user->unidadesNegocio()->sync($data['unidades_negocio'] ?? []);

        return redirect()
            ->route('admin.usuarios.index')
            ->with('success', "Usuário \"{$user->name}\" criado com sucesso. Senha inicial: ".self::DEFAULT_PASSWORD);
    }

    public function edit(User $user): View
    {
        return view('admin.usuarios.edit', [
            'user' => $user,
            'roles' => $this->roles(),
            'selectedRoleIds' => $user->roles->pluck('id'),
            'unidadesNegocio' => $this->unidadesNegocio(),
            'selectedUnidadeNegocioIds' => $user->unidadesNegocio()->pluck('unidades_negocio.id'),
            'isProtected' => $this->isProtected($user),
        ]);
    }

    public function update(UpdateUsuarioRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->update([
            'name' => $data['name'],
            'login' => $data['login'],
            'email' => $data['email'],
        ]);

        $rolesToSync = $this->resolveRoles($data['roles'] ?? []);

        if ($this->isProtected($user)) {
            $programador = Role::query()
                ->where('guard_name', self::GUARD)
                ->where('name', Roles::PROGRAMADOR->value)
                ->first();

            if ($programador && ! $rolesToSync->contains('id', $programador->id)) {
                $rolesToSync->push($programador);
            }
        }

        $user->syncRoles($rolesToSync);
        $user->unidadesNegocio()->sync($data['unidades_negocio'] ?? []);

        return redirect()
            ->route('admin.usuarios.index')
            ->with('success', "Usuário \"{$user->name}\" atualizado com sucesso.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        if ($this->isProtected($user)) {
            return redirect()
                ->route('admin.usuarios.index')
                ->with('error', 'A senha do usuário Programador não pode ser resetada por esta tela.');
        }

        if ($request->user()->is($user)) {
            return redirect()
                ->route('admin.usuarios.index')
                ->with('error', 'Você não pode resetar sua própria senha por aqui. Use a opção de alterar senha no seu perfil.');
        }

        $user->forceFill([
            'password' => Hash::make(self::DEFAULT_PASSWORD),
            'must_change_password' => true,
        ])->save();

        return redirect()
            ->route('admin.usuarios.index')
            ->with('success', 'Senha resetada para a senha padrão. O usuário deverá alterá-la no próximo login.');
    }

    public function desativar(Request $request, User $user): RedirectResponse
    {
        if ($this->isProtected($user)) {
            return redirect()
                ->route('admin.usuarios.index')
                ->with('error', 'O usuário Programador não pode ser desativado.');
        }

        if ($user->hasRole(Roles::PROGRAMADOR->value)) {
            return redirect()
                ->route('admin.usuarios.index')
                ->with('error', 'Usuários com a role Programador não podem ser desativados.');
        }

        if ($request->user()->is($user)) {
            return redirect()
                ->route('admin.usuarios.index')
                ->with('error', 'Você não pode desativar a si mesmo.');
        }

        if (! $user->ativo) {
            return redirect()
                ->route('admin.usuarios.index')
                ->with('info', "Usuário \"{$user->name}\" já estava desativado.");
        }

        $user->forceFill(['ativo' => false])->save();

        return redirect()
            ->route('admin.usuarios.index')
            ->with('success', "Usuário \"{$user->name}\" desativado. Ele não poderá mais acessar o sistema.");
    }

    public function reativar(Request $request, User $user): RedirectResponse
    {
        if ($user->ativo) {
            return redirect()
                ->route('admin.usuarios.index')
                ->with('info', "Usuário \"{$user->name}\" já estava ativo.");
        }

        $user->forceFill(['ativo' => true])->save();

        return redirect()
            ->route('admin.usuarios.index')
            ->with('success', "Usuário \"{$user->name}\" reativado com sucesso.");
    }

    /**
     * @return Collection<int, Role>
     */
    private function roles(): Collection
    {
        return Role::query()
            ->where('guard_name', self::GUARD)
            ->orderBy('name')
            ->get();
    }

    private function unidadesNegocio(): Collection
    {
        return UnidadeNegocio::query()
            ->ativas()
            ->orderBy('nome')
            ->get(['id', 'nome', 'id_cigam']);
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return Collection<int, Role>
     */
    private function resolveRoles(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Role::query()
            ->where('guard_name', self::GUARD)
            ->whereIn('id', array_map('intval', $ids))
            ->get();
    }

    private function isProtected(User $user): bool
    {
        return mb_strtolower((string) $user->email) === self::PROTECTED_EMAIL;
    }
}
