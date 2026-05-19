<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Roles;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUsuarioRequest;
use App\Http\Requests\Admin\UpdateUsuarioRequest;
use App\Models\UnidadeNegocio;
use App\Models\User;
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

    public function index(): View
    {
        $users = User::query()
            ->with(['roles', 'unidadesNegocio:id,nome'])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return view('admin.usuarios.index', [
            'users' => $users,
            'protectedEmail' => self::PROTECTED_EMAIL,
        ]);
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
