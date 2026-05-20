<?php

namespace App\Queries;

use App\Models\GrupoContrato;
use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class GrupoContratoQuery
{
    public const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    public const PER_PAGE_DEFAULT = 20;

    public const SORT_DEFAULT = 'nome';

    public const DIRECTION_DEFAULT = 'asc';

    private const ALLOWED_SORTS = [
        'nome' => 'nome',
        'ativo' => 'ativo',
        'created_at' => 'created_at',
    ];

    /**
     * @return array{search:string, per_page:int|string, sort:string, direction:string}
     */
    public function filtrosFromRequest(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));
        $perPageRaw = (string) $request->query('per_page', (string) self::PER_PAGE_DEFAULT);
        $perPage = $perPageRaw === 'all'
            ? 'all'
            : (in_array((int) $perPageRaw, self::PER_PAGE_OPTIONS, true) ? (int) $perPageRaw : self::PER_PAGE_DEFAULT);
        $sort = (string) $request->query('sort', self::SORT_DEFAULT);
        $direction = mb_strtolower((string) $request->query('direction', self::DIRECTION_DEFAULT));

        return [
            'search' => $search,
            'per_page' => $perPage,
            'sort' => array_key_exists($sort, self::ALLOWED_SORTS) ? $sort : self::SORT_DEFAULT,
            'direction' => in_array($direction, ['asc', 'desc'], true) ? $direction : self::DIRECTION_DEFAULT,
        ];
    }

    /**
     * @param  Builder<GrupoContrato>  $query
     * @param  array{search:string, per_page:int|string, sort:string, direction:string}  $filtros
     * @return Builder<GrupoContrato>
     */
    public function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        if ($filtros['search'] !== '') {
            $search = TextoCadastro::normalizarMaiusculas($filtros['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('nome', 'like', "%{$search}%")
                    ->orWhere('descricao', 'like', "%{$search}%");
            });
        }

        $query->orderBy(self::ALLOWED_SORTS[$filtros['sort']], $filtros['direction'])
            ->orderBy('id');

        return $query;
    }
}
