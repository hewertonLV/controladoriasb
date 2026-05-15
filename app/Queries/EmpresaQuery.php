<?php

namespace App\Queries;

use App\Enums\TipoEmpresaRegistro;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Fornecedor;
use App\Models\UnidadeNegocio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EmpresaQuery
{
    public const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    public const PER_PAGE_DEFAULT = 20;

    public const SORT_DEFAULT = 'nome_exibicao';

    public const DIRECTION_DEFAULT = 'asc';

    private const ALLOWED_SORTS = [
        'tipo_registro' => 'tipo_registro',
        'id_cigam' => 'id_cigam',
        'nome_exibicao' => 'nome_exibicao',
        'fantasia' => 'fantasia',
        'documento' => 'documento',
        'unidade_referencia' => 'unidade_referencia',
        'tipo_pessoa' => 'tipo_pessoa',
        'status' => 'status',
    ];

    private const NUMERIC_SORTS = [
        'id_cigam' => true,
    ];

    /**
     * @return array{
     *   search: string,
     *   per_page: int|string,
     *   status: string|null,
     *   tipo_entidade: string|null,
     *   sort: string,
     *   direction: string
     * }
     */
    public function filtrosFromRequest(Request $request): array
    {
        return $this->normalizarFiltros($request->query());
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *   search: string,
     *   per_page: int|string,
     *   status: string|null,
     *   tipo_entidade: string|null,
     *   sort: string,
     *   direction: string
     * }
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

        $tipoRaw = strtoupper(trim((string) ($input['tipo_entidade'] ?? '')));
        $tipoEntidade = TipoEmpresaRegistro::tryFrom($tipoRaw)?->value
            ?? (in_array($tipoRaw, ['CLIENTE', 'FORNECEDOR', 'UNIDADE_NEGOCIO'], true) ? $tipoRaw : null);

        $sortRaw = (string) ($input['sort'] ?? self::SORT_DEFAULT);
        if ($sortRaw === 'nome') {
            $sortRaw = 'nome_exibicao';
        }
        if ($sortRaw === 'cpf_cnpj') {
            $sortRaw = 'documento';
        }
        if ($sortRaw === 'unidade_negocio') {
            $sortRaw = 'unidade_referencia';
        }
        $sort = array_key_exists($sortRaw, self::ALLOWED_SORTS) ? $sortRaw : self::SORT_DEFAULT;

        $directionRaw = mb_strtolower((string) ($input['direction'] ?? self::DIRECTION_DEFAULT));
        $direction = in_array($directionRaw, ['asc', 'desc'], true) ? $directionRaw : self::DIRECTION_DEFAULT;

        return [
            'search' => $search,
            'per_page' => $perPage,
            'status' => $status,
            'tipo_entidade' => $tipoEntidade,
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    /**
     * @param  Builder<Empresa>  $query
     * @param  array{
     *   search:string,
     *   per_page:int|string,
     *   status:string|null,
     *   tipo_entidade:string|null,
     *   sort:string,
     *   direction:string
     * }  $filtros
     * @return Builder<Empresa>
     */
    public function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        if ($filtros['tipo_entidade'] !== null) {
            try {
                $class = TipoEmpresaRegistro::from($filtros['tipo_entidade'])->classeModelo();
                $query->where('entidade_type', $class);
            } catch (\Throwable) {
                //
            }
        }

        if ($filtros['status'] === '1') {
            $query->where(function (Builder $q): void {
                $q->whereIn('entidade_type', [Cliente::class, Fornecedor::class])
                    ->orWhere(function (Builder $q2): void {
                        $q2->where('entidade_type', UnidadeNegocio::class)
                            ->whereHasMorph(
                                'entidade',
                                [UnidadeNegocio::class],
                                fn (Builder $u) => $u->where('status', true),
                            );
                    });
            });
        } elseif ($filtros['status'] === '0') {
            $query->where('entidade_type', UnidadeNegocio::class)
                ->whereHasMorph(
                    'entidade',
                    [UnidadeNegocio::class],
                    fn (Builder $u) => $u->where('status', false),
                );
        }

        if ($filtros['search'] !== '') {
            $search = $filtros['search'];
            $digits = preg_replace('/\D/', '', $search) ?? '';
            $tipoBuscado = $this->parseTipoPessoaBusca($search);

            $query->where(function (Builder $q) use ($search, $digits, $tipoBuscado): void {
                $q->whereHasMorph(
                    'entidade',
                    [Cliente::class],
                    function (Builder $c) use ($search, $digits): void {
                        $c->where('id_cigam', 'like', "%{$search}%")
                            ->orWhere('razao_social', 'like', "%{$search}%");
                        if ($digits !== '') {
                            $c->orWhere('cnpj_cpf', 'like', "%{$digits}%");
                        }
                    },
                )
                    ->orWhereHasMorph(
                        'entidade',
                        [Fornecedor::class],
                        function (Builder $f) use ($search, $digits): void {
                            $f->where('id_cigam', 'like', "%{$search}%")
                                ->orWhere('razao_social', 'like', "%{$search}%")
                                ->orWhere('fantasia', 'like', "%{$search}%");
                            if ($digits !== '') {
                                $f->orWhere('cnpj_cpf', 'like', "%{$digits}%");
                            }
                        },
                    )
                    ->orWhereHasMorph(
                        'entidade',
                        [UnidadeNegocio::class],
                        function (Builder $u) use ($search, $digits): void {
                            $u->where('id_cigam', 'like', "%{$search}%")
                                ->orWhere('nome', 'like', "%{$search}%")
                                ->orWhere('razao_social', 'like', "%{$search}%");
                            if ($digits !== '') {
                                $u->orWhere('cpf_cnpj', 'like', "%{$digits}%");
                            }
                        },
                    );

                if ($tipoBuscado !== null) {
                    $len = $tipoBuscado === 'FISICA' ? 11 : 14;
                    $q->orWhereHasMorph(
                        'entidade',
                        [Cliente::class, Fornecedor::class, UnidadeNegocio::class],
                        function (Builder $e) use ($len): void {
                            if ($e->getModel() instanceof UnidadeNegocio) {
                                $e->whereRaw('LENGTH(cpf_cnpj) = ?', [$len]);
                            } else {
                                $e->whereRaw('LENGTH(cnpj_cpf) = ?', [$len]);
                            }
                        },
                    );
                }
            });
        }

        $sortKey = self::ALLOWED_SORTS[$filtros['sort']] ?? self::ALLOWED_SORTS[self::SORT_DEFAULT];
        $direction = in_array($filtros['direction'], ['asc', 'desc'], true)
            ? $filtros['direction']
            : self::DIRECTION_DEFAULT;

        $this->aplicarOrdenacao($query, $sortKey, $direction);

        $query->orderBy('empresas.id');

        return $query;
    }

    /**
     * @param  Builder<Empresa>  $query
     */
    private function aplicarOrdenacao(Builder $query, string $sortKey, string $direction): void
    {
        if ($sortKey === 'tipo_registro') {
            $query->orderBy('entidade_type', $direction);

            return;
        }

        if (isset(self::NUMERIC_SORTS[$sortKey])) {
            $expr = $this->expressaoOrdenacao($sortKey);
            $castType = $query->getConnection()->getDriverName() === 'sqlite' ? 'INTEGER' : 'UNSIGNED';
            $query->orderByRaw("CAST(({$expr}) AS {$castType}) {$direction}");

            return;
        }

        $expr = $this->expressaoOrdenacao($sortKey);
        $query->orderByRaw("({$expr}) {$direction}");
    }

    private function expressaoOrdenacao(string $sortKey): string
    {
        $c = Cliente::class;
        $f = Fornecedor::class;
        $u = UnidadeNegocio::class;

        return match ($sortKey) {
            'id_cigam' => 'CASE entidade_type '
                ."WHEN '{$c}' THEN (SELECT id_cigam FROM clientes WHERE id = empresas.entidade_id) "
                ."WHEN '{$f}' THEN (SELECT id_cigam FROM fornecedores WHERE id = empresas.entidade_id) "
                ."WHEN '{$u}' THEN (SELECT id_cigam FROM unidades_negocio WHERE id = empresas.entidade_id) "
                ."ELSE '' END",
            'nome_exibicao' => 'CASE entidade_type '
                ."WHEN '{$c}' THEN (SELECT razao_social FROM clientes WHERE id = empresas.entidade_id) "
                ."WHEN '{$f}' THEN (SELECT razao_social FROM fornecedores WHERE id = empresas.entidade_id) "
                ."WHEN '{$u}' THEN (SELECT nome FROM unidades_negocio WHERE id = empresas.entidade_id) "
                ."ELSE '' END",
            'fantasia' => 'CASE entidade_type '
                ."WHEN '{$f}' THEN (SELECT COALESCE(fantasia, '') FROM fornecedores WHERE id = empresas.entidade_id) "
                ."ELSE '' END",
            'documento' => 'CASE entidade_type '
                ."WHEN '{$c}' THEN (SELECT cnpj_cpf FROM clientes WHERE id = empresas.entidade_id) "
                ."WHEN '{$f}' THEN (SELECT cnpj_cpf FROM fornecedores WHERE id = empresas.entidade_id) "
                ."WHEN '{$u}' THEN (SELECT cpf_cnpj FROM unidades_negocio WHERE id = empresas.entidade_id) "
                ."ELSE '' END",
            'unidade_referencia' => 'CASE entidade_type '
                ."WHEN '{$c}' THEN (SELECT un.id_cigam FROM unidades_negocio un INNER JOIN clientes cl ON cl.id_unidade_negocio = un.id WHERE cl.id = empresas.entidade_id) "
                ."WHEN '{$u}' THEN (SELECT id_cigam FROM unidades_negocio WHERE id = empresas.entidade_id) "
                ."ELSE '' END",
            'tipo_pessoa' => 'CASE entidade_type '
                ."WHEN '{$c}' THEN (SELECT CASE WHEN LENGTH(cnpj_cpf) = 11 THEN 'FISICA' WHEN LENGTH(cnpj_cpf) = 14 THEN 'JURIDICA' ELSE '' END FROM clientes WHERE id = empresas.entidade_id) "
                ."WHEN '{$f}' THEN (SELECT CASE WHEN LENGTH(cnpj_cpf) = 11 THEN 'FISICA' WHEN LENGTH(cnpj_cpf) = 14 THEN 'JURIDICA' ELSE '' END FROM fornecedores WHERE id = empresas.entidade_id) "
                ."WHEN '{$u}' THEN (SELECT CASE WHEN LENGTH(cpf_cnpj) = 11 THEN 'FISICA' WHEN LENGTH(cpf_cnpj) = 14 THEN 'JURIDICA' ELSE '' END FROM unidades_negocio WHERE id = empresas.entidade_id) "
                ."ELSE '' END",
            'status' => 'CASE entidade_type '
                ."WHEN '{$c}' THEN '1' "
                ."WHEN '{$f}' THEN '1' "
                ."WHEN '{$u}' THEN (SELECT CASE WHEN status = 1 THEN '1' ELSE '0' END FROM unidades_negocio WHERE id = empresas.entidade_id) "
                ."ELSE '0' END",
            default => 'CASE entidade_type '
                ."WHEN '{$c}' THEN (SELECT razao_social FROM clientes WHERE id = empresas.entidade_id) "
                ."WHEN '{$f}' THEN (SELECT razao_social FROM fornecedores WHERE id = empresas.entidade_id) "
                ."WHEN '{$u}' THEN (SELECT nome FROM unidades_negocio WHERE id = empresas.entidade_id) "
                ."ELSE '' END",
        };
    }

    private function parseTipoPessoaBusca(string $valor): ?string
    {
        $normalized = mb_strtoupper(trim($valor));
        $normalized = str_replace(['Í', 'Ì', 'Î', 'Á', 'À', 'Â', 'Ã'], ['I', 'I', 'I', 'A', 'A', 'A', 'A'], $normalized);

        return match ($normalized) {
            'F', 'PF', 'FISICA', 'PESSOA FISICA' => 'FISICA',
            'J', 'PJ', 'JURIDICA', 'PESSOA JURIDICA' => 'JURIDICA',
            default => null,
        };
    }
}
