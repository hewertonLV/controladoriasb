<?php

namespace App\Queries;

use App\Models\UnidadeNegocio;
use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class UnidadeNegocioQuery
{
    public const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    public const PER_PAGE_DEFAULT = 20;

    public const SORT_DEFAULT = 'nome';

    public const DIRECTION_DEFAULT = 'asc';

    private const ALLOWED_SORTS = [
        'id_cigam' => 'id_cigam',
        'razao_social' => 'razao_social',
        'nome' => 'nome',
        'custo_operacional' => 'custo_operacional',
        'status' => 'status',
        'possui_estoque' => 'possui_estoque',
        'created_at' => 'created_at',
        'estado' => 'estado',
    ];

    private const NUMERIC_SORTS = [
        'id_cigam' => true,
        'custo_operacional' => true,
    ];

    /**
     * @return array{search: string, per_page: int|string, status: string|null, possui_estoque: string|null, id_estado: string|null, sort: string, direction: string}
     */
    public function filtrosFromRequest(Request $request): array
    {
        return $this->normalizarFiltros($request->query());
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{search: string, per_page: int|string, status: string|null, possui_estoque: string|null, id_estado: string|null, sort: string, direction: string}
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

        $statusRaw = (string) ($input['status'] ?? '');
        $status = in_array($statusRaw, ['0', '1'], true) ? $statusRaw : null;

        $possuiEstoqueRaw = (string) ($input['possui_estoque'] ?? '');
        $possuiEstoque = in_array($possuiEstoqueRaw, ['0', '1'], true) ? $possuiEstoqueRaw : null;

        $idEstadoRaw = (string) ($input['id_estado'] ?? '');
        $idEstado = ctype_digit($idEstadoRaw) && $idEstadoRaw !== '0' ? $idEstadoRaw : null;

        $sortRaw = (string) ($input['sort'] ?? self::SORT_DEFAULT);
        $sort = array_key_exists($sortRaw, self::ALLOWED_SORTS) ? $sortRaw : self::SORT_DEFAULT;

        $directionRaw = mb_strtolower((string) ($input['direction'] ?? self::DIRECTION_DEFAULT));
        $direction = in_array($directionRaw, ['asc', 'desc'], true) ? $directionRaw : self::DIRECTION_DEFAULT;

        return [
            'search' => $search,
            'per_page' => $perPage,
            'status' => $status,
            'possui_estoque' => $possuiEstoque,
            'id_estado' => $idEstado,
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    /**
     * @param  Builder<UnidadeNegocio>  $query
     * @param  array{search:string, per_page:int|string, status:string|null, possui_estoque:string|null, id_estado:string|null, sort:string, direction:string}  $filtros
     * @return Builder<UnidadeNegocio>
     */
    public function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        if ($filtros['status'] !== null) {
            $query->where('status', (bool) $filtros['status']);
        }

        if ($filtros['possui_estoque'] !== null) {
            $query->where('possui_estoque', (bool) $filtros['possui_estoque']);
        }

        if ($filtros['id_estado'] !== null) {
            $query->where('id_estado', (int) $filtros['id_estado']);
        }

        if ($filtros['search'] !== '') {
            $search = $filtros['search'];
            $searchUpper = TextoCadastro::normalizarMaiusculas($search);
            $digits = TextoCadastro::somenteDigitos($search);

            $query->where(function (Builder $q) use ($search, $searchUpper, $digits) {
                $q->where('id_cigam', 'like', "%{$search}%")
                    ->orWhere('razao_social', 'like', "%{$searchUpper}%")
                    ->orWhere('nome', 'like', "%{$searchUpper}%")
                    ->orWhere('cpf_cnpj', 'like', "%{$digits}%");

                if ($digits !== '') {
                    $q->orWhere('cpf_cnpj', 'like', "%{$digits}%");
                }

                $q->orWhereHas('estado', function (Builder $eq) use ($searchUpper): void {
                    $eq->where('nome', 'like', "%{$searchUpper}%")
                        ->orWhere('abreviacao', 'like', "%{$searchUpper}%");
                });
            });
        }

        $sortKey = $filtros['sort'];
        $sortColumn = self::ALLOWED_SORTS[$sortKey] ?? self::ALLOWED_SORTS[self::SORT_DEFAULT];
        $direction = in_array($filtros['direction'], ['asc', 'desc'], true)
            ? $filtros['direction']
            : self::DIRECTION_DEFAULT;

        if ($sortKey === 'estado') {
            $query->leftJoin('estados', 'estados.id', '=', 'unidades_negocio.id_estado')
                ->select('unidades_negocio.*')
                ->orderBy('estados.nome', $direction);
        } elseif (isset(self::NUMERIC_SORTS[$sortColumn])) {
            $castType = $query->getConnection()->getDriverName() === 'sqlite' ? 'REAL' : 'DECIMAL(15,2)';
            if ($sortColumn === 'id_cigam') {
                $castType = $query->getConnection()->getDriverName() === 'sqlite' ? 'INTEGER' : 'UNSIGNED';
            }
            $query->orderByRaw("CAST({$sortColumn} AS {$castType}) {$direction}");
        } else {
            $query->orderBy($sortColumn, $direction);
        }

        $query->orderBy('id');

        $query->with(['estado:id,nome,abreviacao']);

        return $query;
    }
}
