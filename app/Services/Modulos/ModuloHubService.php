<?php

namespace App\Services\Modulos;

use App\Enums\AppModulo;
use App\Enums\Roles;
use App\Models\User;
use App\Support\Modulos\ModuloCard;
use Illuminate\Support\Collection;

class ModuloHubService
{
    public function __construct(
        private readonly RoleModuloService $roleModulos,
    ) {}

    /**
     * @return Collection<int, ModuloCard>
     */
    public function modulosDisponiveis(User $user): Collection
    {
        if ($user->hasRole(Roles::PROGRAMADOR->value)) {
            return collect(AppModulo::cases())
                ->map(fn (AppModulo $modulo) => new ModuloCard($modulo, $modulo->urlEntrada()));
        }

        return $this->roleModulos
            ->modulosDoUsuario($user)
            ->map(fn (AppModulo $modulo) => new ModuloCard($modulo, $modulo->urlEntrada()));
    }

    public function podeAcessarModulo(User $user, AppModulo $modulo): bool
    {
        return $this->modulosDisponiveis($user)
            ->contains(fn (ModuloCard $card) => $card->modulo === $modulo);
    }

    public function urlEntrada(User $user, AppModulo $modulo): string
    {
        $card = $this->modulosDisponiveis($user)
            ->first(fn (ModuloCard $item) => $item->modulo === $modulo);

        if ($card === null) {
            abort(403);
        }

        return $card->urlEntrada;
    }

    public function deveExibirSidebarAdministrativa(?AppModulo $moduloAtivo, User $user): bool
    {
        if ($moduloAtivo !== null) {
            return $moduloAtivo->exibeSidebarAdministrativa();
        }

        return $this->roleModulos
            ->modulosDoUsuario($user)
            ->contains(AppModulo::Administrador);
    }
}
