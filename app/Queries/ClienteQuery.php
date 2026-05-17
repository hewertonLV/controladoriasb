<?php

namespace App\Queries;

use App\Models\Cliente;
use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ClienteQuery
{
    public const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    public const PER_PAGE_DEFAULT = 20;

    public const SORT_DEFAULT = 'razao_social';

    public const DIRECTION_DEFAULT = 'asc';

    private const ALLOWED_SORTS = [
        'id_cigam' => 'id_cigam',
        'razao_social' => 'razao_social',
        'fantasia' => 'fantasia',
        'cnpj_cpf' => 'cnpj_cpf',
        'desconto_nf' => 'desconto_nf',
        'desconto_contrato' => 'desconto_contrato',
        'created_at' => 'created_at',
    ];

    private const NUMERIC_SORTS = [
        'id_cigam' => true,
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
     * @param  Builder<Cliente>  $query
     * @param  array{search:string, per_page:int|string, sort:string, direction:string}  $filtros
     * @return Builder<Cliente>
     */
    public function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        if ($filtros['search'] !== '') {
            $search = $filtros['search'];
            $searchUpper = TextoCadastro::normalizarMaiusculas($search);
            $digits = TextoCadastro::somenteDigitos($search);

            $query->where(function (Builder $q) use ($search, $searchUpper, $digits) {
                $q->where('id_cigam', 'like', "%{$search}%")
                    ->orWhere('razao_social', 'like', "%{$searchUpper}%")
                    ->orWhere('fantasia', 'like', "%{$searchUpper}%")
                    ->orWhere('cnpj_cpf', 'like', "%{$digits}%")
                    ->orWhereHas('praca', fn (Builder $pq) => $pq->where('nome', 'like', "%{$searchUpper}%"))
                    ->orWhereHas('grupo', fn (Builder $gq) => $gq->where('nome', 'like', "%{$searchUpper}%"));

                if ($digits !== '') {
                    $q->orWhere('id_cigam', 'like', "%{$digits}%")
                        ->orWhere('cnpj_cpf', 'like', "%{$digits}%");
                }
            });
        }

        $sortColumn = self::ALLOWED_SORTS[$filtros['sort']] ?? self::ALLOWED_SORTS[self::SORT_DEFAULT];
        $direction = in_array($filtros['direction'], ['asc', 'desc'], true)
            ? $filtros['direction']
            : self::DIRECTION_DEFAULT;

        if (isset(self::NUMERIC_SORTS[$sortColumn])) {
            $castType = $query->getConnection()->getDriverName() === 'sqlite' ? 'INTEGER' : 'UNSIGNED';
            $query->orderByRaw("CAST({$sortColumn} AS {$castType}) {$direction}");
        } else {
            $query->orderBy($sortColumn, $direction);
        }

        $query->orderBy('id');

        return $query;
    }
}
