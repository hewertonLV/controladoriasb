<?php

namespace App\Queries;

use App\Models\Estoque;
use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EstoqueQuery
{
    public const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    public const PER_PAGE_DEFAULT = 20;

    public const SORT_DEFAULT = 'unidade';

    public const DIRECTION_DEFAULT = 'asc';

    private const ALLOWED_SORTS = [
        'unidade' => 'unidades_negocio.nome',
        'fruta' => 'frutas.nome',
        'qtd_fruta_kg' => 'estoques.qtd_fruta_kg',
        'preco_medio_kg' => 'estoques.preco_medio_kg',
        'valor_total' => 'estoques.valor_total_acumulado',
        'created_at' => 'estoques.created_at',
    ];

    /**
     * @return array{search: string, per_page: int|string, id_unidade_negocio: int|null, id_fruta: int|null, sort: string, direction: string}
     */
    public function filtrosFromRequest(Request $request): array
    {
        return $this->normalizarFiltros($request->query());
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{search: string, per_page: int|string, id_unidade_negocio: int|null, id_fruta: int|null, sort: string, direction: string}
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

        $idUn = isset($input['id_unidade_negocio']) && $input['id_unidade_negocio'] !== '' && $input['id_unidade_negocio'] !== null
            ? (int) $input['id_unidade_negocio']
            : null;
        if ($idUn !== null && $idUn <= 0) {
            $idUn = null;
        }

        $idFr = isset($input['id_fruta']) && $input['id_fruta'] !== '' && $input['id_fruta'] !== null
            ? (int) $input['id_fruta']
            : null;
        if ($idFr !== null && $idFr <= 0) {
            $idFr = null;
        }

        $sortRaw = (string) ($input['sort'] ?? self::SORT_DEFAULT);
        $sort = array_key_exists($sortRaw, self::ALLOWED_SORTS) ? $sortRaw : self::SORT_DEFAULT;

        $directionRaw = mb_strtolower((string) ($input['direction'] ?? self::DIRECTION_DEFAULT));
        $direction = in_array($directionRaw, ['asc', 'desc'], true) ? $directionRaw : self::DIRECTION_DEFAULT;

        return [
            'search' => $search,
            'per_page' => $perPage,
            'id_unidade_negocio' => $idUn,
            'id_fruta' => $idFr,
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    /**
     * @param  Builder<Estoque>  $query
     * @param  array{search:string, per_page:int|string, id_unidade_negocio:int|null, id_fruta:int|null, sort:string, direction:string}  $filtros
     * @return Builder<Estoque>
     */
    public function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        if ($filtros['id_unidade_negocio'] !== null) {
            $query->where('estoques.id_unidade_negocio', $filtros['id_unidade_negocio']);
        }

        if ($filtros['id_fruta'] !== null) {
            $query->where('estoques.id_fruta', $filtros['id_fruta']);
        }

        if ($filtros['search'] !== '') {
            $s = $filtros['search'];
            $sUpper = TextoCadastro::normalizarMaiusculas($s);
            $query->where(function (Builder $q) use ($s, $sUpper) {
                $q->where('unidades_negocio.id_cigam', 'like', '%'.$s.'%')
                    ->orWhere('unidades_negocio.nome', 'like', '%'.$sUpper.'%')
                    ->orWhere('frutas.id_cigam', 'like', '%'.$s.'%')
                    ->orWhere('frutas.nome', 'like', '%'.$sUpper.'%');
            });
        }

        $sortColumn = self::ALLOWED_SORTS[$filtros['sort']] ?? self::ALLOWED_SORTS[self::SORT_DEFAULT];
        $direction = in_array($filtros['direction'], ['asc', 'desc'], true)
            ? $filtros['direction']
            : self::DIRECTION_DEFAULT;

        $query->orderBy($sortColumn, $direction);
        $query->orderBy('estoques.id');

        return $query;
    }
}
