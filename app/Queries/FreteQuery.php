<?php

namespace App\Queries;

use App\Enums\FreteStatusSituacao;
use App\Models\Frete;
use App\Support\TextoCadastro;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class FreteQuery
{
    public const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    public const PER_PAGE_DEFAULT = 20;

    public const SORT_DEFAULT = 'nome';

    public const DIRECTION_DEFAULT = 'asc';

    private const ALLOWED_SORTS = [
        'nome' => 'nome',
        'valor' => 'valor',
        'status_situacao' => 'status_situacao',
        'valor_fruta_kg' => 'valor_fruta_kg',
        'created_at' => 'created_at',
    ];

    private const NUMERIC_SORTS = [
        'valor' => true,
        'valor_fruta_kg' => true,
    ];

    /**
     * @return array{search: string, per_page: int|string, status_situacao: string|null, sort: string, direction: string}
     */
    public function filtrosFromRequest(Request $request): array
    {
        return $this->normalizarFiltros($request->query());
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{search: string, per_page: int|string, status_situacao: string|null, sort: string, direction: string}
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

        $statusRaw = mb_strtoupper((string) ($input['status_situacao'] ?? ''));
        $statusSituacao = in_array($statusRaw, FreteStatusSituacao::values(), true) ? $statusRaw : null;

        $sortRaw = (string) ($input['sort'] ?? self::SORT_DEFAULT);
        $sort = array_key_exists($sortRaw, self::ALLOWED_SORTS) ? $sortRaw : self::SORT_DEFAULT;

        $directionRaw = mb_strtolower((string) ($input['direction'] ?? self::DIRECTION_DEFAULT));
        $direction = in_array($directionRaw, ['asc', 'desc'], true) ? $directionRaw : self::DIRECTION_DEFAULT;

        return [
            'search' => $search,
            'per_page' => $perPage,
            'status_situacao' => $statusSituacao,
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    /**
     * @param  Builder<Frete>  $query
     * @param  array{search:string, per_page:int|string, status_situacao:string|null, sort:string, direction:string}  $filtros
     * @return Builder<Frete>
     */
    public function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        $query->with('veiculo');

        if ($filtros['status_situacao'] !== null) {
            $query->where('status_situacao', $filtros['status_situacao']);
        }

        if ($filtros['search'] !== '') {
            $search = $filtros['search'];
            $searchUpper = TextoCadastro::normalizarMaiusculas($search);
            $digits = TextoCadastro::somenteDigitos($search);

            $query->where(function (Builder $q) use ($search, $searchUpper, $digits) {
                $q->where('nome', 'like', "%{$searchUpper}%")
                    ->orWhere('descricao', 'like', "%{$search}%")
                    ->orWhere('status_situacao', 'like', "%{$searchUpper}%");

                if ($digits !== '') {
                    $q->orWhereHas('veiculo', function (Builder $vq) use ($digits) {
                        $vq->where('id_sbs', 'like', "%{$digits}%");
                    });
                }
            });
        }

        $sortColumn = self::ALLOWED_SORTS[$filtros['sort']] ?? self::ALLOWED_SORTS[self::SORT_DEFAULT];
        $direction = in_array($filtros['direction'], ['asc', 'desc'], true)
            ? $filtros['direction']
            : self::DIRECTION_DEFAULT;

        if (isset(self::NUMERIC_SORTS[$sortColumn])) {
            $castType = $query->getConnection()->getDriverName() === 'sqlite' ? 'REAL' : 'DECIMAL(15,2)';
            $query->orderByRaw("CAST({$sortColumn} AS {$castType}) {$direction}");
        } else {
            $query->orderBy($sortColumn, $direction);
        }

        $query->orderBy('id');

        return $query;
    }
}
