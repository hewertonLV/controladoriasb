<?php

namespace App\Providers;

use App\Contracts\Movimentacoes\ReprocessaEntradasDevolucaoDestino;
use App\Contracts\Movimentacoes\ReprocessaEstoqueDestinoCompra;
use App\Contracts\Movimentacoes\ReprocessaSaidasDescarteOrigem;
use App\Contracts\Movimentacoes\ReprocessaSaidasDoacaoOrigem;
use App\Contracts\Movimentacoes\ReprocessaSaidasTransferenciaOrigem;
use App\Contracts\Movimentacoes\ReprocessaSaidasVendaOrigem;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Enums\Roles;
use App\Models\Cliente;
use App\Models\Fornecedor;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Observers\ClienteObserver;
use App\Observers\FornecedorObserver;
use App\Observers\MovimentacaoObserver;
use App\Observers\UnidadeNegocioObserver;
use App\Services\Movimentacoes\ReplayEstoqueCompraService;
use App\Services\Movimentacoes\ReplayEstoqueDescarteService;
use App\Services\Movimentacoes\ReplayEstoqueDevolucaoService;
use App\Services\Movimentacoes\ReplayEstoqueDoacaoService;
use App\Services\Movimentacoes\ReplayEstoqueTransferenciaService;
use App\Services\Movimentacoes\ReplayEstoqueVendaService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReprocessaEstoqueDestinoCompra::class, ReplayEstoqueCompraService::class);
        $this->app->bind(ReprocessaSaidasTransferenciaOrigem::class, ReplayEstoqueTransferenciaService::class);
        $this->app->bind(ReprocessaSaidasDoacaoOrigem::class, ReplayEstoqueDoacaoService::class);
        $this->app->bind(ReprocessaSaidasDescarteOrigem::class, ReplayEstoqueDescarteService::class);
        $this->app->bind(ReprocessaSaidasVendaOrigem::class, ReplayEstoqueVendaService::class);
        $this->app->bind(ReprocessaEntradasDevolucaoDestino::class, ReplayEstoqueDevolucaoService::class);
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

        Route::bind('transferenciaOrigem', function (string $value): Movimentacao {
            $anchor = (int) $value;

            return Movimentacao::query()
                ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
                ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
                ->where('transferencia_origem_id', $anchor)
                ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
                ->orderByDesc('versao')
                ->orderByDesc('id')
                ->firstOrFail();
        });

        Route::bind('movimentacaoDoacao', function (string $value): Movimentacao {
            return Movimentacao::query()
                ->whereKey($value)
                ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Doacao->value)
                ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
                ->firstOrFail();
        });

        Route::bind('movimentacaoDescarte', function (string $value): Movimentacao {
            return Movimentacao::query()
                ->whereKey($value)
                ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Descarte->value)
                ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
                ->firstOrFail();
        });

        Route::bind('movimentacaoVenda', function (string $value): Movimentacao {
            return Movimentacao::query()
                ->whereKey($value)
                ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
                ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
                ->firstOrFail();
        });

        Route::bind('movimentacaoDevolucao', function (string $value): Movimentacao {
            return Movimentacao::query()
                ->whereKey($value)
                ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Devolucao->value)
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
