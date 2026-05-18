<?php

namespace App\Support\Movimentacoes;

use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class FrutasComEstoqueOrigem
{
    /**
     * @return EloquentCollection<int, Fruta>
     */
    public static function listar(): EloquentCollection
    {
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
