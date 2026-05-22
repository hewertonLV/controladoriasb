<?php

namespace App\Support\Movimentacoes;

use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Services\Empresas\EmpresaRegistryService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class FrutasComEstoqueOrigem
{
    /**
     * @return EloquentCollection<int, Fruta>
     */
    public static function listar(): EloquentCollection
    {
        self::garantirEmpresasParaUnidadesComEstoquePositivo();

        $empresaIdsPorFruta = Estoque::query()
            ->select('estoques.id_fruta', 'empresas.id as id_empresa')
            ->join('unidades_negocio', 'unidades_negocio.id', '=', 'estoques.id_unidade_negocio')
            ->join('empresas', function ($join): void {
                $join->on('empresas.entidade_id', '=', 'unidades_negocio.id')
                    ->where('empresas.entidade_type', UnidadeNegocio::class);
            })
            ->where(function ($query): void {
                $query->where('estoques.qtd_fruta_um', '>', 0)
                    ->orWhere('estoques.qtd_fruta_kg', '>', 0);
            })
            ->get()
            ->groupBy('id_fruta')
            ->map(fn ($linhas): array => $linhas->pluck('id_empresa')->map(fn ($id): int => (int) $id)->unique()->values()->all());

        if ($empresaIdsPorFruta->isEmpty()) {
            return new EloquentCollection;
        }

        return Fruta::query()
            ->whereIn('id', $empresaIdsPorFruta->keys()->all())
            ->where('kg_por_unidade_medicao', '>', 0)
            ->orderBy('nome')
            ->get()
            ->each(function (Fruta $fruta) use ($empresaIdsPorFruta): void {
                $fruta->setAttribute('estoque_origem_empresa_ids', $empresaIdsPorFruta->get($fruta->id, []));
            });
    }

    private static function garantirEmpresasParaUnidadesComEstoquePositivo(): void
    {
        $unidadeIds = Estoque::query()
            ->where(function ($query): void {
                $query->where('qtd_fruta_um', '>', 0)
                    ->orWhere('qtd_fruta_kg', '>', 0);
            })
            ->distinct()
            ->pluck('id_unidade_negocio')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($unidadeIds === []) {
            return;
        }

        $unidadesComEmpresa = Empresa::query()
            ->where('entidade_type', UnidadeNegocio::class)
            ->whereIn('entidade_id', $unidadeIds)
            ->pluck('entidade_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $semEmpresa = array_values(array_diff($unidadeIds, $unidadesComEmpresa));

        if ($semEmpresa === []) {
            return;
        }

        $registry = app(EmpresaRegistryService::class);

        foreach (UnidadeNegocio::query()->whereIn('id', $semEmpresa)->get() as $unidade) {
            $registry->garantirRegistro($unidade, auth()->user());
        }
    }

    /**
     * Catálogo leve para filtro de frutas por origem física no formulário (JS).
     *
     * @param  EloquentCollection<int, Fruta>  $frutas
     * @return list<array{id: int, nome: string, origens: list<int>}>
     */
    public static function catalogoJs(EloquentCollection $frutas): array
    {
        return $frutas
            ->map(fn (Fruta $fruta): array => [
                'id' => (int) $fruta->id,
                'nome' => (string) $fruta->nome,
                'origens' => array_values(array_map(
                    intval(...),
                    $fruta->getAttribute('estoque_origem_empresa_ids') ?? [],
                )),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  iterable<int, Empresa>  $empresas
     * @return list<int>
     */
    public static function idsEmpresas(iterable $empresas): array
    {
        $ids = [];

        foreach ($empresas as $empresa) {
            $ids[] = (int) $empresa->id;
        }

        return array_values(array_unique($ids));
    }
}
