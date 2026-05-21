<?php

namespace App\Services\Dashboard;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Models\Empresa;
use App\Models\Movimentacao;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Services\Permissoes\UnidadeNegocioAccessService;
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
    public function forUser(User $user, ?array $unidadeIdsFiltro = null): array
    {
        $inicio = now()->startOfMonth()->startOfDay();
        $fim = now()->endOfDay();

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
            'periodo' => [
                'inicio' => $inicio->toDateString(),
                'fim' => $fim->toDateString(),
                'label' => $inicio->translatedFormat('F/Y').' (01 a '.$fim->format('d/m').')',
            ],
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
        ];
    }

    /**
     * @return list<array{id: int, nome: string}>
     */
    private function unidadesDisponiveis(User $user): array
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

        if ($filtro === null || $filtro === []) {
            return $permitidos;
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
            ? "date(data_movimentacao)"
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
