<?php

namespace App\Services\Permissoes;

use App\Enums\Roles;
use App\Models\Empresa;
use App\Models\UnidadeNegocio;
use App\Models\User;

final class UnidadeNegocioAccessService
{
    public const MENSAGEM_SEM_ACESSO = 'Você não possui permissão para movimentar esta Unidade de Negócio.';

    public function isAdministradorUnidades(User $user): bool
    {
        return $user->hasAnyRole([
            Roles::PROGRAMADOR->value,
            Roles::ADMINISTRADOR->value,
        ]);
    }

    public function canAccess(?User $user, int $unidadeNegocioId): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->isAdministradorUnidades($user)) {
            return true;
        }

        return $user->podeMovimentarUnidade($unidadeNegocioId);
    }

    public function canCompra(User $user, int $destinoId): bool
    {
        return $this->canAccess($user, $destinoId);
    }

    public function canTransferencia(User $user, int $origemId): bool
    {
        return $this->canAccess($user, $origemId);
    }

    public function canDoacao(User $user, int $origemId): bool
    {
        return $this->canAccess($user, $origemId);
    }

    public function canDescarte(User $user, int $origemId): bool
    {
        return $this->canAccess($user, $origemId);
    }

    public function canDevolucao(User $user, int $destinoId): bool
    {
        return $this->canAccess($user, $destinoId);
    }

    public function canConversao(User $user, int $origemId): bool
    {
        return $this->canAccess($user, $origemId);
    }

    public function canVenda(User $user, int $origemId): bool
    {
        return $this->canAccess($user, $origemId);
    }

    /**
     * Retorna null quando o usuário pode acessar todas as unidades.
     *
     * @return list<int>|null
     */
    public function unidadeIdsPermitidas(?User $user): ?array
    {
        if ($user === null) {
            return [];
        }

        if ($this->isAdministradorUnidades($user)) {
            return null;
        }

        return $user->unidadeNegocioIdsPermitidas();
    }

    /**
     * Retorna null quando o usuário pode acessar todas as unidades.
     *
     * @return list<int>|null
     */
    public function empresaIdsPermitidas(?User $user): ?array
    {
        $unidadeIds = $this->unidadeIdsPermitidas($user);
        if ($unidadeIds === null) {
            return null;
        }

        if ($unidadeIds === []) {
            return [];
        }

        return Empresa::query()
            ->where('entidade_type', UnidadeNegocio::class)
            ->whereIn('entidade_id', $unidadeIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function unidadeIdDaEmpresa(int $empresaId): ?int
    {
        $empresa = Empresa::query()->with('entidade')->find($empresaId);

        return $empresa?->entidade instanceof UnidadeNegocio
            ? (int) $empresa->entidade->id
            : null;
    }
}
