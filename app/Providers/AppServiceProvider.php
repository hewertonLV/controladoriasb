<?php

namespace App\Providers;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\Roles;
use App\Models\Cliente;
use App\Models\Fornecedor;
use App\Models\Movimentacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Observers\ClienteObserver;
use App\Observers\FornecedorObserver;
use App\Observers\MovimentacaoObserver;
use App\Observers\UnidadeNegocioObserver;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // O projeto usa o tema Highdmin (Bootstrap 5). O paginator padrão
        // do Laravel renderiza markup Tailwind (com SVGs grandes para
        // anterior/próximo), que fica desalinhado. Aqui forçamos o template
        // Bootstrap 5 (`<ul class="pagination"><li class="page-item">...`),
        // que também é compatível com o interceptor de cliques em
        // `a.page-link` do componente `<x-admin.data-table>`.
        Paginator::useBootstrapFive();

        Route::bind('movimentacao', function (string $value): Movimentacao {
            return Movimentacao::query()
                ->whereKey($value)
                ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Compra->value)
                ->firstOrFail();
        });

        Gate::before(function (User $user) {
            return $user->hasRole(Roles::PROGRAMADOR->value) ? true : null;
        });

        UnidadeNegocio::observe(UnidadeNegocioObserver::class);
        Cliente::observe(ClienteObserver::class);
        Fornecedor::observe(FornecedorObserver::class);
        Movimentacao::observe(MovimentacaoObserver::class);
    }
}
