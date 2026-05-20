<?php

namespace App\Services\Dashboard;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Models\Cliente;
use App\Models\Estoque;
use App\Models\Movimentacao;
use App\Models\Praca;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Models\Veiculo;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

final class DashboardStatsService
{
    public function __construct(
        private readonly UnidadeNegocioAccessService $access,
    ) {}

    /**
     * @return array{
     *     acesso_total: bool,
     *     escopo_label: string,
     *     totais: array{
     *         unidades: int,
     *         clientes: int,
     *         veiculos: int,
     *         pracas: int,
     *         estoques: int,
     *         movimentacoes: int,
     *         movimentacoes_mes: int
     *     },
     *     unidades: list<array{
     *         id: int,
     *         nome: string,
     *         clientes: int,
     *         veiculos: int,
     *         pracas: int,
     *         estoques: int
     *     }>,
     *     movimentacoes_por_tipo: list<array{tipo: string, total: int}>,
     *     movimentacoes_recentes: list<array{
     *         id: int,
     *         tipo: string,
     *         fruta: string,
     *         data: string,
     *         url: string|null
     *     }>
     * }
     */
    public function forUser(User $user): array
    {
        $acessoTotal = $this->access->isAdministradorUnidades($user);
        $unidadeIds = $this->access->unidadeIdsPermitidas($user);

        $unidades = UnidadeNegocio::query()
            ->permitidasPara($user)
            ->orderBy('nome')
            ->withCount(['clientes', 'veiculos', 'pracas', 'estoques'])
            ->get(['id', 'nome']);

        $movimentacoesQuery = $this->movimentacoesAcessiveis($user);

        $movimentacoesPorTipo = (clone $movimentacoesQuery)
            ->selectRaw('categoria_movimentacao_id, COUNT(*) as total')
            ->groupBy('categoria_movimentacao_id')
            ->pluck('total', 'categoria_movimentacao_id')
            ->all();

        $porTipo = [];
        foreach (CategoriaMovimentacaoTipo::cases() as $tipo) {
            $total = (int) ($movimentacoesPorTipo[$tipo->value] ?? 0);
            if ($total === 0) {
                continue;
            }
            $porTipo[] = [
                'tipo' => $tipo->nomeLegivel(),
                'total' => $total,
            ];
        }

        $recentes = (clone $movimentacoesQuery)
            ->with(['fruta:id,nome'])
            ->orderByDesc('data_movimentacao')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['id', 'categoria_movimentacao_id', 'data_movimentacao', 'id_fruta'])
            ->map(fn (Movimentacao $movimentacao): array => [
                'id' => $movimentacao->id,
                'tipo' => $this->nomeTipoMovimentacao($movimentacao->categoria_movimentacao_id),
                'fruta' => $movimentacao->fruta?->nome ?? '—',
                'data' => $movimentacao->data_movimentacao instanceof Carbon
                    ? $movimentacao->data_movimentacao->format('d/m/Y H:i')
                    : '—',
                'url' => $this->urlMovimentacao($movimentacao),
            ])
            ->all();

        return [
            'acesso_total' => $acessoTotal,
            'escopo_label' => $this->rotuloEscopo($acessoTotal, $unidadeIds, $unidades->count()),
            'totais' => [
                'unidades' => $unidades->count(),
                'clientes' => $this->contarPorUnidade(Cliente::query(), $user),
                'veiculos' => $this->contarPorUnidade(Veiculo::query(), $user),
                'pracas' => $this->contarPorUnidade(Praca::query(), $user),
                'estoques' => $this->contarPorUnidade(Estoque::query(), $user),
                'movimentacoes' => (clone $movimentacoesQuery)->count(),
                'movimentacoes_mes' => (clone $movimentacoesQuery)
                    ->where('data_movimentacao', '>=', now()->startOfMonth())
                    ->count(),
            ],
            'unidades' => $unidades
                ->map(fn (UnidadeNegocio $unidade): array => [
                    'id' => $unidade->id,
                    'nome' => $unidade->nome,
                    'clientes' => (int) $unidade->clientes_count,
                    'veiculos' => (int) $unidade->veiculos_count,
                    'pracas' => (int) $unidade->pracas_count,
                    'estoques' => (int) $unidade->estoques_count,
                ])
                ->all(),
            'movimentacoes_por_tipo' => $porTipo,
            'movimentacoes_recentes' => $recentes,
        ];
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function contarPorUnidade(Builder $query, User $user): int
    {
        return $this->filtrarColunaUnidade($query, 'id_unidade_negocio', $user)->count();
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function filtrarColunaUnidade(Builder $query, string $column, User $user): Builder
    {
        $unidadeIds = $this->access->unidadeIdsPermitidas($user);

        if ($unidadeIds === null) {
            return $query;
        }

        if ($unidadeIds === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn($column, $unidadeIds);
    }

    /**
     * @return Builder<Movimentacao>
     */
    private function movimentacoesAcessiveis(User $user): Builder
    {
        $query = Movimentacao::query()
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value);

        $empresaIds = $this->access->empresaIdsPermitidas($user);
        $unidadeIds = $this->access->unidadeIdsPermitidas($user);

        if ($empresaIds === null) {
            return $query;
        }

        if ($empresaIds === [] && $unidadeIds === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $sub) use ($empresaIds, $unidadeIds): void {
            if ($empresaIds !== []) {
                $sub->where(function (Builder $empresas) use ($empresaIds): void {
                    $empresas->whereIn('id_empresa_origem', $empresaIds)
                        ->orWhereIn('id_empresa_destino', $empresaIds);
                });
            }

            if ($unidadeIds !== []) {
                $sub->orWhere(function (Builder $unidades) use ($unidadeIds): void {
                    $unidades->whereIn('id_unidade_negocio_faturamento', $unidadeIds)
                        ->orWhereIn('id_unidade_negocio_retorno', $unidadeIds);
                });
            }
        });
    }

    /**
     * @param  list<int>|null  $unidadeIds
     */
    private function rotuloEscopo(bool $acessoTotal, ?array $unidadeIds, int $unidadesVisiveis): string
    {
        if ($acessoTotal) {
            return 'Visão de todas as unidades de negócio';
        }

        if ($unidadeIds === []) {
            return 'Nenhuma unidade vinculada ao seu usuário';
        }

        if ($unidadesVisiveis === 1) {
            return 'Visão da sua unidade de negócio';
        }

        return "Visão de {$unidadesVisiveis} unidades vinculadas";
    }

    private function nomeTipoMovimentacao(int $categoriaId): string
    {
        $tipo = CategoriaMovimentacaoTipo::tryFrom($categoriaId);

        return $tipo?->nomeLegivel() ?? 'Movimentação';
    }

    private function urlMovimentacao(Movimentacao $movimentacao): ?string
    {
        $tipo = CategoriaMovimentacaoTipo::tryFrom((int) $movimentacao->categoria_movimentacao_id);

        if ($tipo === null) {
            return null;
        }

        return match ($tipo) {
            CategoriaMovimentacaoTipo::Compra => route('admin.movimentacoes.compras.show', $movimentacao),
            CategoriaMovimentacaoTipo::Transferencia => route('admin.movimentacoes.transferencias.show', $movimentacao),
            CategoriaMovimentacaoTipo::Venda => route('admin.movimentacoes.vendas.show', $movimentacao),
            CategoriaMovimentacaoTipo::Doacao => route('admin.movimentacoes.doacoes.show', $movimentacao),
            CategoriaMovimentacaoTipo::Descarte => route('admin.movimentacoes.descartes.show', $movimentacao),
            CategoriaMovimentacaoTipo::Devolucao => route('admin.movimentacoes.devolucoes.show', $movimentacao),
            CategoriaMovimentacaoTipo::ConversaoEmbalagem => route('admin.movimentacoes.conversoes-embalagem.show', $movimentacao),
        };
    }
}
