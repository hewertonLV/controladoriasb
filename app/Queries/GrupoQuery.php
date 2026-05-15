<?php

namespace App\Queries;

use App\Models\Grupo;
use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class GrupoQuery
{
    public const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    public const PER_PAGE_DEFAULT = 20;

    public const SORT_DEFAULT = 'nome';

    public const DIRECTION_DEFAULT = 'asc';

    private const ALLOWED_SORTS = [
        'nome' => 'nome',
        'created_at' => 'created_at',
    ];

    /**
     * @return array{search: string, per_page: int|string, sort: string, direction: string}
     */
    public function filtrosFromRequest(Request $request): array
    {
        return $this->normalizarFiltros($request->query());
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{search: string, per_page: int|string, sort: string, direction: string}
     */
    public function normalizarFiltros(array $input): array
    {
        $search = trim((string) ($input['search'] ?? ''));

        $perPageRaw = (string) ($input['per_page'] ?? (string) self::PER_PAGE_DEFAULT);
        if ($perPageRaw === 'all') {
            $perPage = 'all';
        } else {
            $candidate = (int) $perPageRaw;
            $perPage = in_array($candidate, self::PER_PAGE_OPTIONS, true) ? $candidate : self::PER_PAGE_DEFAULT;
        }

        $sortRaw = (string) ($input['sort'] ?? self::SORT_DEFAULT);
        $sort = array_key_exists($sortRaw, self::ALLOWED_SORTS) ? $sortRaw : self::SORT_DEFAULT;

        $directionRaw = mb_strtolower((string) ($input['direction'] ?? self::DIRECTION_DEFAULT));
        $direction = in_array($directionRaw, ['asc', 'desc'], true) ? $directionRaw : self::DIRECTION_DEFAULT;

        return [
            'search' => $search,
            'per_page' => $perPage,
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    /**
     * @param  Builder<Grupo>  $query
     * @param  array{search:string, per_page:int|string, sort:string, direction:string}  $filtros
     * @return Builder<Grupo>
     */
    public function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        if ($filtros['search'] !== '') {
            $searchUpper = TextoCadastro::normalizarMaiusculas($filtros['search']);

            $query->where('nome', 'like', "%{$searchUpper}%");
        }

        $sortColumn = self::ALLOWED_SORTS[$filtros['sort']] ?? self::ALLOWED_SORTS[self::SORT_DEFAULT];
        $direction = in_array($filtros['direction'], ['asc', 'desc'], true)
            ? $filtros['direction']
            : self::DIRECTION_DEFAULT;

        $query->orderBy($sortColumn, $direction);
        $query->orderBy('id');

        return $query;
    }
}
