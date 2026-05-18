<?php

namespace App\Support;

use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\UnidadeNegocio;
use Illuminate\Database\Eloquent\Builder;

final class EmpresaEntidadeQuery
{
    /**
     * @param  iterable<int, int|string>  $entidadeIds
     * @return Builder<Empresa>
     */
    public static function porEntidadeIds(string $entidadeType, iterable $entidadeIds): Builder
    {
        $ids = [];

        foreach ($entidadeIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $query = Empresa::query()
            ->where('entidade_type', $entidadeType);

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('entidade_id', array_values(array_unique($ids)));
    }

    /**
     * @return Builder<Empresa>
     */
    public static function unidadesComEstoque(bool $somenteComEstoqueCadastrado = false): Builder
    {
        $unidadesQuery = UnidadeNegocio::query()
            ->where('possui_estoque', true);

        if ($somenteComEstoqueCadastrado) {
            $unidadesQuery->whereIn(
                'id',
                Estoque::query()
                    ->select('id_unidade_negocio')
                    ->distinct(),
            );
        }

        return self::porEntidadeIds(
            UnidadeNegocio::class,
            $unidadesQuery->pluck('id'),
        );
    }
}
