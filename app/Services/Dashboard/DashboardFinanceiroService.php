<?php

namespace App\Services\Dashboard;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Models\Empresa;
use App\Models\Movimentacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use App\Support\Dashboard\DashboardPeriodo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class DashboardFinanceiroService
{
    public function __construct(
        private readonly UnidadeNegocioAccessService $access,
    ) {}

    /**
     * @param  list<int>|null  $unidadeIdsFiltro
     * @return array{
     *     periodo: array{inicio: string, fim: string, label: string},
     *     filtro_unidades: list<int>,
     *     unidades_disponiveis: list<array{id: int, nome: string}>,
     *     cards: array<string, array{reais: float, kg: float}>,
     *     grafico_diario: array{
     *         categorias: list<string>,
     *         faturado: list<float>,
     *         vendido_kg: list<float>,
     *         doado: list<float>,
     *         descartado: list<float>
     *     },
     *     pizza_rentabilidade: list<array{label: string, valor: float, valor_exibicao: float}>
     * }
     */
    /**
     * Payload vazio para pré-visualização (ex.: testes de layout guest).
     *
     * @return array<string, mixed>
     */
    public function vazio(): array
    {
        $inicio = now()->startOfMonth();
        $fim = now();

        return [
            'periodo' => [
                'inicio' => $inicio->toDateString(),
                'fim' => $fim->toDateString(),
                'label' => $inicio->translatedFormat('F/Y'),
            ],
            'filtro_unidades' => [],
            'unidades_disponiveis' => [],
            'cards' => [
                'faturado' => ['reais' => 0.0, 'kg' => 0.0],
                'devolucao' => ['reais' => 0.0, 'kg' => 0.0],
                'liquido' => ['reais' => 0.0, 'kg' => 0.0],
                'rentabilidade' => ['reais' => 0.0, 'kg' => 0.0, 'percentual' => null],
                'descartado' => ['reais' => 0.0, 'kg' => 0.0],
                'doado' => ['reais' => 0.0, 'kg' => 0.0],
            ],
            'grafico_diario' => [
                'categorias' => [],
                'faturado' => [],
                'vendido_kg' => [],
                'doado' => [],
                'descartado' => [],
            ],
            'pizza_rentabilidade' => [
                ['label' => 'Sem movimentação', 'valor' => 1.0, 'valor_exibicao' => 0.0],
            ],
            'grafico_rentabilidade_unidades' => [
                'categorias' => [],
                'reais' => [],
                'percentual' => [],
            ],
        ];
    }

    /**
     * @param  list<int>|null  $unidadeIdsFiltro
     * @return array{
     *     periodo: array{inicio: string, fim: string, label: string},
     *     filtro_unidades: list<int>,
     *     unidades_disponiveis: list<array{id: int, nome: string}>,
     *     cards: array<string, array{reais: float, kg: float}>,
     *     grafico_diario: array{
     *         categorias: list<string>,
     *         faturado: list<float>,
     *         vendido_kg: list<float>,
     *         doado: list<float>,
     *         descartado: list<float>
     *     },
     *     pizza_rentabilidade: list<array{label: string, valor: float, valor_exibicao: float}>
     * }
     */
    public function forUser(User $user, ?array $unidadeIdsFiltro = null, ?string $mes = null, ?string $dia = null): array
    {
        $periodo = DashboardPeriodo::resolver($mes, $dia);
        $inicio = $periodo->inicio;
        $fim = $periodo->fim;

        $unidadesDisponiveis = $this->unidadesDisponiveis($user);
        $unidadeIdsAplicados = $this->resolverUnidadeIds($user, $unidadeIdsFiltro, $unidadesDisponiveis);
        $empresaIds = $this->empresaIdsDasUnidades($unidadeIdsAplicados, $user);

        $vendas = $this->queryVendas($empresaIds, $inicio, $fim);
        $devolucoes = $this->queryDevolucoes($empresaIds, $inicio, $fim);
        $doacoes = $this->queryPorOrigem(CategoriaMovimentacaoTipo::Doacao, $empresaIds, $inicio, $fim);
        $descartes = $this->queryPorOrigem(CategoriaMovimentacaoTipo::Descarte, $empresaIds, $inicio, $fim);

        $faturadoReais = $this->sumColuna($vendas, 'valor_nf_total');
        $faturadoKg = $this->sumColuna($vendas, 'qtd_fruta_kg');
        $devolucaoReais = $this->sumColuna($devolucoes, 'valor_devolucao_total');
        $devolucaoKg = $this->sumColuna($devolucoes, 'qtd_fruta_kg');
        $resultadoVendas = $this->sumColuna($vendas, 'resultado_movimentacao');
        $resultadoDevolucoes = $this->sumColuna($devolucoes, 'resultado_devolucao');
        $doadoReais = $this->sumColuna($doacoes, 'valor_total_movimentacao');
        $doadoKg = $this->sumColuna($doacoes, 'qtd_fruta_kg');
        $descartadoReais = $this->sumColuna($descartes, 'valor_total_movimentacao');
        $descartadoKg = $this->sumColuna($descartes, 'qtd_fruta_kg');
        $rentabilidadeReais = $resultadoVendas + $resultadoDevolucoes;

        return [
            'periodo' => $periodo->toArray(),
            'filtro_unidades' => $unidadeIdsAplicados,
            'unidades_disponiveis' => $unidadesDisponiveis,
            'cards' => [
                'faturado' => $this->parReaisKg($faturadoReais, $faturadoKg),
                'devolucao' => $this->parReaisKg($devolucaoReais, $devolucaoKg),
                'liquido' => $this->parReaisKg($faturadoReais - $devolucaoReais, $faturadoKg - $devolucaoKg),
                'rentabilidade' => $this->cardRentabilidade(
                    $rentabilidadeReais,
                    $faturadoKg - $devolucaoKg,
                    $faturadoReais,
                ),
                'descartado' => $this->parReaisKg($descartadoReais, $descartadoKg),
                'doado' => $this->parReaisKg($doadoReais, $doadoKg),
            ],
            'grafico_diario' => $this->montarGraficoDiario($inicio, $fim, $vendas, $devolucoes, $doacoes, $descartes),
            'pizza_rentabilidade' => $this->montarPizzaRentabilidade($resultadoVendas, $resultadoDevolucoes),
            'grafico_rentabilidade_unidades' => $this->montarGraficoRentabilidadeUnidades(
                $this->unidadesDoGrafico($unidadesDisponiveis, $unidadeIdsAplicados),
                $inicio,
                $fim,
            ),
        ];
    }

    /**
     * @return list<int>
     */
    public function unidadeIdsPadrao(User $user): array
    {
        return array_column($this->unidadesDisponiveis($user), 'id');
    }

    /**
     * @return list<array{id: int, nome: string}>
     */
    public function unidadesDisponiveis(User $user): array
    {
        return UnidadeNegocio::query()
            ->permitidasPara($user)
            ->orderBy('nome')
            ->get(['id', 'nome'])
            ->map(fn (UnidadeNegocio $u): array => [
                'id' => $u->id,
                'nome' => $u->nome,
            ])
            ->all();
    }

    /**
     * @param  list<array{id: int, nome: string}>  $disponiveis
     * @param  list<int>|null  $filtro
     * @return list<int>
     */
    private function resolverUnidadeIds(User $user, ?array $filtro, array $disponiveis): array
    {
        $permitidos = array_column($disponiveis, 'id');

        if ($filtro === null) {
            return $permitidos;
        }

        if ($filtro === []) {
            return [];
        }

        $filtro = array_values(array_unique(array_map('intval', $filtro)));

        return array_values(array_intersect($filtro, $permitidos));
    }

    /**
     * @param  list<int>  $unidadeIds
     * @return list<int>|null
     */
    private function empresaIdsDasUnidades(array $unidadeIds, User $user): ?array
    {
        if ($unidadeIds === []) {
            return [];
        }

        $permitidasTodas = $this->access->unidadeIdsPermitidas($user) === null
            && ($this->access->empresaIdsPermitidas($user) === null);

        if ($permitidasTodas && $unidadeIds === $this->todasUnidadeIds()) {
            return null;
        }

        return Empresa::query()
            ->where('entidade_type', UnidadeNegocio::class)
            ->whereIn('entidade_id', $unidadeIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function todasUnidadeIds(): array
    {
        return UnidadeNegocio::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @param  list<int>|null  $empresaIds
     * @return Builder<Movimentacao>
     */
    private function queryVendas(?array $empresaIds, Carbon $inicio, Carbon $fim): Builder
    {
        $query = Movimentacao::query()
            ->vigentesParaCalculo()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->whereBetween('data_movimentacao', [$inicio, $fim]);

        $this->aplicarFiltroEmpresaOrigem($query, $empresaIds);

        return $query;
    }

    /**
     * @param  list<int>|null  $empresaIds
     * @return Builder<Movimentacao>
     */
    private function queryDevolucoes(?array $empresaIds, Carbon $inicio, Carbon $fim): Builder
    {
        $query = Movimentacao::query()
            ->vigentesParaCalculo()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Devolucao->value)
            ->whereNotNull('movimentacao_venda_origem_id')
            ->whereBetween('data_movimentacao', [$inicio, $fim]);

        if ($empresaIds === []) {
            $query->whereRaw('0 = 1');
        } elseif ($empresaIds !== null) {
            $query->whereHas('vendaOrigem', fn (Builder $venda): Builder => $venda->whereIn('id_empresa_origem', $empresaIds));
        }

        return $query;
    }

    /**
     * @param  list<int>|null  $empresaIds
     * @return Builder<Movimentacao>
     */
    private function queryPorOrigem(CategoriaMovimentacaoTipo $tipo, ?array $empresaIds, Carbon $inicio, Carbon $fim): Builder
    {
        $query = Movimentacao::query()
            ->vigentesParaCalculo()
            ->where('categoria_movimentacao_id', $tipo->value)
            ->whereBetween('data_movimentacao', [$inicio, $fim]);

        $this->aplicarFiltroEmpresaOrigem($query, $empresaIds);

        return $query;
    }

    /**
     * @param  Builder<Movimentacao>  $query
     * @param  list<int>|null  $empresaIds
     */
    private function aplicarFiltroEmpresaOrigem(Builder $query, ?array $empresaIds): void
    {
        if ($empresaIds === null) {
            return;
        }

        if ($empresaIds === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereIn('id_empresa_origem', $empresaIds);
    }

    /**
     * @param  Builder<Movimentacao>  $query
     */
    private function sumColuna(Builder $query, string $coluna): float
    {
        return round((float) (clone $query)->sum($coluna), 2);
    }

    /**
     * @return array{reais: float, kg: float}
     */
    private function parReaisKg(float $reais, float $kg): array
    {
        return [
            'reais' => round($reais, 2),
            'kg' => round($kg, 2),
        ];
    }

    /**
     * Margem % = resultado rentabilidade ÷ faturado (mesma regra do relatório por loja).
     *
     * @return array{reais: float, kg: float, percentual: float|null}
     */
    private function cardRentabilidade(float $reais, float $kg, float $faturadoReais): array
    {
        $card = $this->parReaisKg($reais, $kg);
        $card['percentual'] = $faturadoReais > 0
            ? round(($reais / $faturadoReais) * 100, 2)
            : null;

        return $card;
    }

    /**
     * @param  Builder<Movimentacao>  $vendas
     * @param  Builder<Movimentacao>  $devolucoes
     * @param  Builder<Movimentacao>  $doacoes
     * @param  Builder<Movimentacao>  $descartes
     * @return array{
     *     categorias: list<string>,
     *     faturado: list<float>,
     *     vendido_kg: list<float>,
     *     doado: list<float>,
     *     descartado: list<float>
     * }
     */
    private function montarGraficoDiario(
        Carbon $inicio,
        Carbon $fim,
        Builder $vendas,
        Builder $devolucoes,
        Builder $doacoes,
        Builder $descartes,
    ): array {
        $dias = $this->diasDoPeriodo($inicio, $fim);

        $faturadoPorDia = $this->agruparPorDia($vendas, 'valor_nf_total');
        $vendidoKgPorDia = $this->agruparPorDia($vendas, 'qtd_fruta_kg');
        $doadoPorDia = $this->agruparPorDia($doacoes, 'valor_total_movimentacao');
        $descartadoPorDia = $this->agruparPorDia($descartes, 'valor_total_movimentacao');

        $categorias = [];
        $faturado = [];
        $vendidoKg = [];
        $doado = [];
        $descartado = [];

        foreach ($dias as $dia) {
            $chave = $dia->toDateString();
            $categorias[] = $dia->format('d/m');
            $faturado[] = round((float) ($faturadoPorDia[$chave] ?? 0), 2);
            $vendidoKg[] = round((float) ($vendidoKgPorDia[$chave] ?? 0), 2);
            $doado[] = round((float) ($doadoPorDia[$chave] ?? 0), 2);
            $descartado[] = round((float) ($descartadoPorDia[$chave] ?? 0), 2);
        }

        return [
            'categorias' => $categorias,
            'faturado' => $faturado,
            'vendido_kg' => $vendidoKg,
            'doado' => $doado,
            'descartado' => $descartado,
        ];
    }

    /**
     * @return list<Carbon>
     */
    private function diasDoPeriodo(Carbon $inicio, Carbon $fim): array
    {
        $dias = [];
        $cursor = $inicio->copy()->startOfDay();

        while ($cursor->lte($fim)) {
            $dias[] = $cursor->copy();
            $cursor->addDay();
        }

        return $dias;
    }

    /**
     * @param  Builder<Movimentacao>  $query
     * @return array<string, float>
     */
    private function agruparPorDia(Builder $query, string $coluna): array
    {
        $driver = DB::connection()->getDriverName();
        $expressaoData = $driver === 'sqlite'
            ? 'date(data_movimentacao)'
            : 'DATE(data_movimentacao)';

        return (clone $query)
            ->selectRaw("{$expressaoData} as dia, SUM({$coluna}) as total")
            ->groupBy('dia')
            ->orderBy('dia')
            ->pluck('total', 'dia')
            ->mapWithKeys(fn ($total, $dia): array => [(string) $dia => (float) $total])
            ->all();
    }

    /**
     * @param  list<array{id: int, nome: string}>  $disponiveis
     * @param  list<int>  $unidadeIdsAplicados
     * @return list<array{id: int, nome: string}>
     */
    private function unidadesDoGrafico(array $disponiveis, array $unidadeIdsAplicados): array
    {
        $ids = array_flip($unidadeIdsAplicados);

        return array_values(array_filter(
            $disponiveis,
            static fn (array $unidade): bool => isset($ids[$unidade['id']]),
        ));
    }

    /**
     * @param  list<array{id: int, nome: string}>  $unidades
     * @return array{
     *     categorias: list<string>,
     *     reais: list<float>,
     *     percentual: list<float>
     * }
     */
    private function montarGraficoRentabilidadeUnidades(array $unidades, Carbon $inicio, Carbon $fim): array
    {
        if ($unidades === []) {
            return [
                'categorias' => [],
                'reais' => [],
                'percentual' => [],
            ];
        }

        $unidadeIds = array_column($unidades, 'id');
        $empresaPorUnidade = Empresa::query()
            ->where('entidade_type', UnidadeNegocio::class)
            ->whereIn('entidade_id', $unidadeIds)
            ->get(['id', 'entidade_id'])
            ->keyBy('entidade_id');

        $empresaIds = $empresaPorUnidade->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($empresaIds === []) {
            return [
                'categorias' => [],
                'reais' => [],
                'percentual' => [],
            ];
        }

        $vendasPorEmpresa = $this->totaisVendasPorEmpresaOrigem($empresaIds, $inicio, $fim);
        $devolucoesPorEmpresa = $this->totaisDevolucoesPorEmpresaOrigem($empresaIds, $inicio, $fim);

        $linhas = [];

        foreach ($unidades as $unidade) {
            $empresa = $empresaPorUnidade->get($unidade['id']);
            if ($empresa === null) {
                continue;
            }

            $empresaId = (int) $empresa->id;
            $faturado = (float) ($vendasPorEmpresa[$empresaId]['faturado'] ?? 0);
            $resultadoVendas = (float) ($vendasPorEmpresa[$empresaId]['resultado'] ?? 0);
            $resultadoDevolucoes = (float) ($devolucoesPorEmpresa[$empresaId] ?? 0);
            $rentabilidade = round($resultadoVendas + $resultadoDevolucoes, 2);

            $linhas[] = [
                'nome' => $unidade['nome'],
                'reais' => $rentabilidade,
                'percentual' => $faturado > 0
                    ? round(($rentabilidade / $faturado) * 100, 2)
                    : 0.0,
            ];
        }

        usort($linhas, static fn (array $a, array $b): int => $b['reais'] <=> $a['reais']);

        return [
            'categorias' => array_column($linhas, 'nome'),
            'reais' => array_column($linhas, 'reais'),
            'percentual' => array_column($linhas, 'percentual'),
        ];
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<int, array{faturado: float, resultado: float}>
     */
    private function totaisVendasPorEmpresaOrigem(array $empresaIds, Carbon $inicio, Carbon $fim): array
    {
        return Movimentacao::query()
            ->vigentesParaCalculo()
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->whereBetween('data_movimentacao', [$inicio, $fim])
            ->whereIn('id_empresa_origem', $empresaIds)
            ->selectRaw('id_empresa_origem, SUM(valor_nf_total) as faturado, SUM(resultado_movimentacao) as resultado')
            ->groupBy('id_empresa_origem')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                (int) $row->id_empresa_origem => [
                    'faturado' => (float) $row->faturado,
                    'resultado' => (float) $row->resultado,
                ],
            ])
            ->all();
    }

    /**
     * @param  list<int>  $empresaIds
     * @return array<int, float>
     */
    private function totaisDevolucoesPorEmpresaOrigem(array $empresaIds, Carbon $inicio, Carbon $fim): array
    {
        $tabela = (new Movimentacao)->getTable();

        $ativo = MovimentacaoStatusRegistro::ATIVO->value;

        return Movimentacao::query()
            ->withoutGlobalScopes()
            ->from("{$tabela} as devolucoes")
            ->join("{$tabela} as vendas", 'devolucoes.movimentacao_venda_origem_id', '=', 'vendas.id')
            ->whereNull('devolucoes.deleted_at')
            ->whereNull('vendas.deleted_at')
            ->where('devolucoes.status_registro', $ativo)
            ->where('vendas.status_registro', $ativo)
            ->where('devolucoes.categoria_movimentacao_id', CategoriaMovimentacaoTipo::Devolucao->value)
            ->whereBetween('devolucoes.data_movimentacao', [$inicio, $fim])
            ->whereIn('vendas.id_empresa_origem', $empresaIds)
            ->selectRaw('vendas.id_empresa_origem as empresa_origem_id, SUM(devolucoes.resultado_devolucao) as resultado')
            ->groupBy('vendas.id_empresa_origem')
            ->pluck('resultado', 'empresa_origem_id')
            ->mapWithKeys(fn ($total, $empresaId): array => [(int) $empresaId => (float) $total])
            ->all();
    }

    /**
     * @return list<array{label: string, valor: float, valor_exibicao: float}>
     */
    private function montarPizzaRentabilidade(float $resultadoVendas, float $resultadoDevolucoes): array
    {
        $fatias = [];

        if (abs($resultadoVendas) >= 0.01) {
            $fatias[] = [
                'label' => 'Resultado vendas',
                'valor' => abs($resultadoVendas),
                'valor_exibicao' => $resultadoVendas,
            ];
        }

        if (abs($resultadoDevolucoes) >= 0.01) {
            $fatias[] = [
                'label' => 'Resultado devoluções',
                'valor' => abs($resultadoDevolucoes),
                'valor_exibicao' => $resultadoDevolucoes,
            ];
        }

        if ($fatias === []) {
            $fatias[] = [
                'label' => 'Sem movimentação',
                'valor' => 1.0,
                'valor_exibicao' => 0.0,
            ];
        }

        return $fatias;
    }
}
