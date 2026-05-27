<?php

namespace App\Services\Relatorios;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use App\Support\EmpresaEntidadeQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class RentabilidadeLojaService
{
    public function __construct(
        private readonly UnidadeNegocioAccessService $access,
    ) {}

    /**
     * @param  array{
     *     data_inicio: string,
     *     data_fim: string,
     *     id_empresa_origem?: int|string|null,
     *     id_empresa_destino?: int|string|null,
     *     agrupamento?: string
     * }  $filtros
     * @return array{
     *     filtros: array<string, mixed>,
     *     agrupamento: string,
     *     linhas: list<array<string, mixed>>,
     *     totais: array<string, float>
     * }
     */
    public function gerar(User $user, array $filtros): array
    {
        $dataInicio = Carbon::parse((string) $filtros['data_inicio'])->startOfDay();
        $dataFim = Carbon::parse((string) $filtros['data_fim'])->endOfDay();
        $agrupamento = ($filtros['agrupamento'] ?? 'cliente') === 'detalhe' ? 'detalhe' : 'cliente';

        $idOrigem = $this->intOpcional($filtros['id_empresa_origem'] ?? null);
        $idDestino = $this->intOpcional($filtros['id_empresa_destino'] ?? null);

        $linhas = [];

        foreach ($this->vendas($user, $dataInicio, $dataFim, $idOrigem, $idDestino) as $venda) {
            $this->acumularVenda($linhas, $venda, $agrupamento);
        }

        foreach ($this->devolucoes($user, $dataInicio, $dataFim, $idOrigem, $idDestino) as $devolucao) {
            $this->acumularDevolucao($linhas, $devolucao, $agrupamento);
        }

        $linhasOrdenadas = collect($linhas)
            ->sortBy([
                ['cliente_nome', 'asc'],
                ['unidade_origem_nome', 'asc'],
                ['fruta_nome', 'asc'],
            ])
            ->values()
            ->map(fn (array $linha): array => $this->finalizarLinha($linha))
            ->all();

        return [
            'filtros' => [
                'data_inicio' => $dataInicio->toDateString(),
                'data_fim' => $dataFim->toDateString(),
                'id_empresa_origem' => $idOrigem,
                'id_empresa_destino' => $idDestino,
                'agrupamento' => $agrupamento,
            ],
            'agrupamento' => $agrupamento,
            'linhas' => $linhasOrdenadas,
            'totais' => $this->somarTotais($linhasOrdenadas),
        ];
    }

    /**
     * @return array{
     *     unidades_origem: Collection<int, Empresa>,
     *     clientes: Collection<int, Empresa>
     * }
     */
    public function opcoesFiltro(User $user): array
    {
        $unidades = EmpresaEntidadeQuery::unidadesComEstoque()
            ->with('entidade')
            ->orderBy('id')
            ->get()
            ->filter(fn (Empresa $e): bool => $this->empresaPermitidaComoOrigem($user, $e));

        $clientesQuery = Empresa::query()
            ->where('entidade_type', Cliente::class)
            ->with('entidade')
            ->orderBy('id');

        $unidadeIds = $this->access->unidadeIdsPermitidas($user);
        if ($unidadeIds !== null) {
            if ($unidadeIds === []) {
                $clientesQuery->whereRaw('0 = 1');
            } else {
                $clientesQuery->whereHasMorph('entidade', [Cliente::class], function (Builder $q) use ($unidadeIds): void {
                    $q->whereIn('id_unidade_negocio', $unidadeIds);
                });
            }
        }

        return [
            'unidades_origem' => $unidades->values(),
            'clientes' => $clientesQuery->get(),
        ];
    }

    /**
     * @return Collection<int, Movimentacao>
     */
    private function vendas(
        User $user,
        Carbon $dataInicio,
        Carbon $dataFim,
        ?int $idOrigem,
        ?int $idDestino,
    ): Collection {
        $query = Movimentacao::query()
            ->with(['empresaOrigem.entidade', 'empresaDestino.entidade', 'unidadeCentroResultado', 'fruta'])
            ->vigentesParaCalculo()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->whereBetween('data_movimentacao', [$dataInicio, $dataFim]);

        $this->aplicarEscopoOrigem($query, $user);

        if ($idOrigem !== null) {
            $query->where('id_empresa_origem', $idOrigem);
        }

        if ($idDestino !== null) {
            $query->where('id_empresa_destino', $idDestino);
        }

        return $query
            ->orderBy('data_movimentacao')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Movimentacao>
     */
    private function devolucoes(
        User $user,
        Carbon $dataInicio,
        Carbon $dataFim,
        ?int $idOrigem,
        ?int $idDestino,
    ): Collection {
        $query = Movimentacao::query()
            ->with([
                'empresaOrigem.entidade',
                'fruta',
                'vendaOrigem.empresaOrigem.entidade',
                'vendaOrigem.empresaDestino.entidade',
                'vendaOrigem.fruta',
            ])
            ->vigentesParaCalculo()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Devolucao->value)
            ->whereBetween('data_movimentacao', [$dataInicio, $dataFim])
            ->whereNotNull('movimentacao_venda_origem_id');

        if ($idDestino !== null) {
            $query->where('id_empresa_origem', $idDestino);
        }

        $empresaIds = $this->access->empresaIdsPermitidas($user);
        if ($empresaIds !== null) {
            if ($empresaIds === []) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereHas('vendaOrigem', function (Builder $venda) use ($empresaIds, $idOrigem): void {
                    $venda->whereIn('id_empresa_origem', $empresaIds);
                    if ($idOrigem !== null) {
                        $venda->where('id_empresa_origem', $idOrigem);
                    }
                });
            }
        } elseif ($idOrigem !== null) {
            $query->whereHas('vendaOrigem', fn (Builder $venda): Builder => $venda->where('id_empresa_origem', $idOrigem));
        }

        return $query
            ->orderBy('data_movimentacao')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, array<string, mixed>>  $linhas
     */
    private function acumularVenda(array &$linhas, Movimentacao $venda, string $agrupamento): void
    {
        $clienteId = (int) $venda->id_empresa_destino;
        $origemId = $this->resolverEmpresaCentroResultado($venda);
        $frutaId = (int) $venda->id_fruta;

        $chave = $this->chaveLinha($clienteId, $origemId, $frutaId, $agrupamento);
        $linha = $linhas[$chave] ?? $this->linhaVazia($clienteId, $origemId, $frutaId, $venda, $agrupamento);

        $linha['venda_qtd_kg'] += (float) $venda->qtd_fruta_kg;
        $linha['venda_qtd_um'] += (float) $venda->qtd_fruta_um;
        $linha['venda_valor_nf'] += (float) $venda->valor_nf_total;
        $linha['venda_custo_saida'] += (float) $venda->valor_custo_saida;
        $linha['venda_frete'] += (float) $venda->valor_frete_rateio;
        $linha['venda_resultado'] += (float) $venda->resultado_movimentacao;
        $linha['venda_itens']++;

        $linhas[$chave] = $linha;
    }

    /**
     * @param  array<string, array<string, mixed>>  $linhas
     */
    private function acumularDevolucao(array &$linhas, Movimentacao $devolucao, string $agrupamento): void
    {
        $venda = $devolucao->vendaOrigem;
        if ($venda === null) {
            return;
        }

        $clienteId = (int) $venda->id_empresa_destino;
        $origemId = $this->resolverEmpresaCentroResultado($venda);
        $frutaId = (int) $venda->id_fruta;

        $chave = $this->chaveLinha($clienteId, $origemId, $frutaId, $agrupamento);
        $linha = $linhas[$chave] ?? $this->linhaVazia($clienteId, $origemId, $frutaId, $venda, $agrupamento);

        $linha['dev_qtd_kg'] += (float) $devolucao->qtd_fruta_kg;
        $linha['dev_qtd_um'] += (float) $devolucao->qtd_fruta_um;
        $linha['dev_valor_nf'] += (float) $devolucao->valor_devolucao_total;
        $linha['dev_custo'] += (float) $devolucao->valor_custo_devolucao;
        $linha['dev_resultado'] += (float) $devolucao->resultado_devolucao;
        $linha['dev_itens']++;

        $linhas[$chave] = $linha;
    }

    /**
     * @return array<string, mixed>
     */
    private function linhaVazia(int $clienteId, int $origemId, int $frutaId, Movimentacao $referencia, string $agrupamento): array
    {
        $cliente = $referencia->empresaDestino ?? Empresa::query()->with('entidade')->find($clienteId);
        $origem = Empresa::query()->with('entidade')->find($origemId)
            ?? $referencia->empresaOrigem
            ?? Empresa::query()->with('entidade')->find($origemId);
        $fruta = $referencia->fruta;

        return [
            'cliente_id' => $clienteId,
            'cliente_nome' => $cliente?->nomeExibicao() ?? '—',
            'unidade_origem_id' => $origemId,
            'unidade_origem_nome' => $agrupamento === 'detalhe' ? ($origem?->nomeExibicao() ?? '—') : '—',
            'fruta_id' => $frutaId,
            'fruta_nome' => $agrupamento === 'detalhe' ? ($fruta?->nome ?? '—') : '—',
            'venda_qtd_kg' => 0.0,
            'venda_qtd_um' => 0.0,
            'venda_valor_nf' => 0.0,
            'venda_custo_saida' => 0.0,
            'venda_frete' => 0.0,
            'venda_resultado' => 0.0,
            'venda_itens' => 0,
            'dev_qtd_kg' => 0.0,
            'dev_qtd_um' => 0.0,
            'dev_valor_nf' => 0.0,
            'dev_custo' => 0.0,
            'dev_resultado' => 0.0,
            'dev_itens' => 0,
        ];
    }

    private function chaveLinha(int $clienteId, int $origemId, int $frutaId, string $agrupamento): string
    {
        if ($agrupamento === 'detalhe') {
            return "{$clienteId}|{$origemId}|{$frutaId}";
        }

        return (string) $clienteId;
    }

    /**
     * @param  array<string, mixed>  $linha
     * @return array<string, mixed>
     */
    private function finalizarLinha(array $linha): array
    {
        $linha['resultado_liquido'] = round($linha['venda_resultado'] + $linha['dev_resultado'], 2);
        $linha['custo_medio_kg'] = $linha['venda_qtd_kg'] > 0
            ? round($linha['venda_custo_saida'] / $linha['venda_qtd_kg'], 2)
            : 0.0;
        $linha['margem_percentual'] = $linha['venda_valor_nf'] > 0
            ? round(($linha['resultado_liquido'] / $linha['venda_valor_nf']) * 100, 2)
            : null;

        foreach ([
            'venda_qtd_kg', 'venda_qtd_um', 'venda_valor_nf', 'venda_custo_saida', 'venda_frete', 'venda_resultado',
            'dev_qtd_kg', 'dev_qtd_um', 'dev_valor_nf', 'dev_custo', 'dev_resultado', 'resultado_liquido', 'custo_medio_kg',
        ] as $campo) {
            if (is_float($linha[$campo])) {
                $linha[$campo] = round($linha[$campo], 2);
            }
        }

        return $linha;
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     * @return array<string, float>
     */
    private function somarTotais(array $linhas): array
    {
        $totais = [
            'venda_qtd_kg' => 0.0,
            'venda_valor_nf' => 0.0,
            'venda_custo_saida' => 0.0,
            'venda_frete' => 0.0,
            'venda_resultado' => 0.0,
            'dev_qtd_kg' => 0.0,
            'dev_valor_nf' => 0.0,
            'dev_custo' => 0.0,
            'dev_resultado' => 0.0,
            'resultado_liquido' => 0.0,
        ];

        foreach ($linhas as $linha) {
            foreach ($totais as $chave => $valor) {
                $totais[$chave] += (float) $linha[$chave];
            }
        }

        foreach ($totais as $chave => $valor) {
            $totais[$chave] = round($valor, 2);
        }

        $totais['margem_percentual'] = $totais['venda_valor_nf'] > 0
            ? round(($totais['resultado_liquido'] / $totais['venda_valor_nf']) * 100, 2)
            : 0.0;

        return $totais;
    }

    /**
     * @param  Builder<Movimentacao>  $query
     */
    private function aplicarEscopoOrigem(Builder $query, User $user): void
    {
        $empresaIds = $this->access->empresaIdsPermitidas($user);
        if ($empresaIds === null) {
            return;
        }

        if ($empresaIds === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereIn('id_empresa_origem', $empresaIds);
    }

    private function empresaPermitidaComoOrigem(User $user, Empresa $empresa): bool
    {
        if (! $empresa->entidade instanceof UnidadeNegocio) {
            return false;
        }

        return $this->access->canAccess($user, (int) $empresa->entidade->id);
    }

    private function intOpcional(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function resolverEmpresaCentroResultado(Movimentacao $venda): int
    {
        $centroUnidadeId = $venda->id_unidade_negocio_centro_resultado ?? $venda->id_unidade_negocio_faturamento;

        if ($centroUnidadeId !== null) {
            $empresaCentro = Empresa::query()
                ->where('entidade_type', UnidadeNegocio::class)
                ->where('entidade_id', (int) $centroUnidadeId)
                ->value('id');

            if ($empresaCentro !== null) {
                return (int) $empresaCentro;
            }
        }

        return (int) $venda->id_empresa_origem;
    }
}
