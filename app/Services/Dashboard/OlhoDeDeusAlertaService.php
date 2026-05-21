<?php

namespace App\Services\Dashboard;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\OlhoDeDeusAlertaTipo;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use App\Models\User;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use App\Services\Relatorios\RentabilidadeLojaService;
use App\Support\Dashboard\DashboardPeriodo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class OlhoDeDeusAlertaService
{
    public function __construct(
        private readonly UnidadeNegocioAccessService $access,
        private readonly RentabilidadeLojaService $rentabilidadeLoja,
    ) {}

    /**
     * @return array{
     *     server_time: string,
     *     proximo_poll_ms: int,
     *     periodo: array{inicio: string, fim: string, mes: string, label: string},
     *     alertas: list<array<string, mixed>>
     * }
     */
    public function poll(User $user, ?string $mes = null, ?Carbon $since = null, bool $cargaInicial = false): array
    {
        $periodo = DashboardPeriodo::resolver($mes);

        if ($cargaInicial) {
            $movimentacoes = $this->movimentacoesNoPeriodo($user, $periodo->inicio, $periodo->fim);
        } else {
            $since ??= $periodo->inicio;
            $movimentacoes = $this->movimentacoesRecentes($user, $since, $periodo->inicio, $periodo->fim);
        }

        $alertas = [];
        $limite = (int) config('olho_de_deus.max_alertas_por_poll', 50);

        foreach ($movimentacoes as $movimentacao) {
            foreach ($this->detectar($movimentacao, $user, $periodo) as $alerta) {
                $alertas[] = $alerta;

                if (count($alertas) >= $limite) {
                    break 2;
                }
            }
        }

        return [
            'server_time' => now()->toIso8601String(),
            'proximo_poll_ms' => (int) config('olho_de_deus.poll_interval_ms', 45_000),
            'periodo' => $periodo->toArray(),
            'alertas' => $alertas,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function detectar(Movimentacao $movimentacao, User $user, DashboardPeriodo $periodo): array
    {
        $tipoCategoria = CategoriaMovimentacaoTipo::tryFrom((int) $movimentacao->categoria_movimentacao_id);

        if ($tipoCategoria === null) {
            return [];
        }

        return match ($tipoCategoria) {
            CategoriaMovimentacaoTipo::Venda => $this->detectarVenda($movimentacao, $user, $periodo),
            CategoriaMovimentacaoTipo::Devolucao => $this->detectarDevolucao($movimentacao, $user, $periodo),
            CategoriaMovimentacaoTipo::Descarte => $this->detectarPerdaOperacional($movimentacao, OlhoDeDeusAlertaTipo::DescartePerdaElevada),
            CategoriaMovimentacaoTipo::Doacao => $this->detectarPerdaOperacional($movimentacao, OlhoDeDeusAlertaTipo::DoacaoPerdaElevada),
            CategoriaMovimentacaoTipo::Transferencia,
            CategoriaMovimentacaoTipo::Compra => $this->detectarFrete($movimentacao),
            default => [],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function detectarVenda(Movimentacao $venda, User $user, DashboardPeriodo $periodo): array
    {
        if ((int) $venda->status_movimentacao_id !== StatusMovimentacao::ID_SAIDA) {
            return [];
        }

        $alertas = [];
        $precoMedioKg = (float) $venda->preco_medio_fruta_kg;
        $precoMedioUm = (float) $venda->preco_medio_fruta_um;
        $precoVendaKg = (float) $venda->valor_nf_kg;
        $precoVendaUm = (float) $venda->valor_nf_um;
        $freteKg = (float) $venda->valor_frete_kg;
        $resultado = (float) $venda->resultado_movimentacao;
        $limiteFrete = (float) config('olho_de_deus.frete_kg_maximo', 0.50);

        if ($precoVendaKg > 0 && $precoMedioKg > 0 && $precoVendaKg < $precoMedioKg) {
            $alertas[] = $this->montarAlerta(
                $venda,
                OlhoDeDeusAlertaTipo::VendaPrecoAbaixoCustoKg,
                sprintf(
                    'Venda #%d: preço NF/kg (R$ %s) menor que custo médio (R$ %s) — %s / %s.',
                    $venda->id,
                    $this->fmt($precoVendaKg),
                    $this->fmt($precoMedioKg),
                    $venda->fruta?->nome ?? 'Fruta',
                    $this->rotuloEmpresas($venda),
                ),
                [
                    'preco_venda_kg' => $precoVendaKg,
                    'preco_custo_kg' => $precoMedioKg,
                ],
            );
        }

        if ($precoVendaUm > 0 && $precoMedioUm > 0 && $precoVendaUm < $precoMedioUm) {
            $alertas[] = $this->montarAlerta(
                $venda,
                OlhoDeDeusAlertaTipo::VendaPrecoAbaixoCustoUm,
                sprintf(
                    'Venda #%d: preço NF/UM (R$ %s) menor que custo médio/UM (R$ %s).',
                    $venda->id,
                    $this->fmt($precoVendaUm),
                    $this->fmt($precoMedioUm),
                ),
                [
                    'preco_venda_um' => $precoVendaUm,
                    'preco_custo_um' => $precoMedioUm,
                ],
            );
        }

        if ($freteKg > $limiteFrete) {
            $alertas[] = $this->montarAlerta(
                $venda,
                OlhoDeDeusAlertaTipo::FreteKgElevado,
                sprintf(
                    'Venda #%d: frete/kg R$ %s acima do limite de R$ %s.',
                    $venda->id,
                    $this->fmt($freteKg),
                    $this->fmt($limiteFrete),
                ),
                ['frete_kg' => $freteKg, 'limite' => $limiteFrete],
            );
        }

        if ($resultado < 0) {
            $alertas[] = $this->montarAlerta(
                $venda,
                OlhoDeDeusAlertaTipo::RentabilidadeVendaNegativa,
                sprintf(
                    'Venda #%d com resultado negativo de R$ %s (%s).',
                    $venda->id,
                    $this->fmt($resultado),
                    $this->rotuloEmpresas($venda),
                ),
                ['resultado' => $resultado],
            );
        }

        $valorNf = (float) $venda->valor_nf_total;
        $custoSaida = (float) $venda->valor_custo_saida;
        if ($valorNf > 0 && $custoSaida > 0 && $valorNf < $custoSaida) {
            $alertas[] = $this->montarAlerta(
                $venda,
                OlhoDeDeusAlertaTipo::VendaAbaixoCustoTotal,
                sprintf(
                    'Venda #%d: NF total R$ %s abaixo do custo de saída R$ %s.',
                    $venda->id,
                    $this->fmt($valorNf),
                    $this->fmt($custoSaida),
                ),
                ['valor_nf' => $valorNf, 'custo_saida' => $custoSaida],
            );
        }

        $linhaRentabilidade = $this->linhaRentabilidadeNoPeriodo($venda, $user, $periodo);
        if ($linhaRentabilidade !== null && (float) $linhaRentabilidade['resultado_liquido'] < 0) {
            $alertas[] = $this->montarAlerta(
                $venda,
                OlhoDeDeusAlertaTipo::RentabilidadeLojaNegativa,
                sprintf(
                    'Cliente %s na unidade %s: rentabilidade acumulada no mês R$ %s (fruta %s).',
                    $linhaRentabilidade['cliente_nome'],
                    $linhaRentabilidade['unidade_origem_nome'],
                    $this->fmt((float) $linhaRentabilidade['resultado_liquido']),
                    $linhaRentabilidade['fruta_nome'],
                ),
                [
                    'resultado_liquido_mes' => (float) $linhaRentabilidade['resultado_liquido'],
                    'cliente_id' => $linhaRentabilidade['cliente_id'],
                ],
            );
        }

        return $alertas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function detectarDevolucao(Movimentacao $devolucao, User $user, DashboardPeriodo $periodo): array
    {
        $alertas = [];
        $resultado = (float) $devolucao->resultado_devolucao;

        if ($resultado < 0) {
            $alertas[] = $this->montarAlerta(
                $devolucao,
                OlhoDeDeusAlertaTipo::DevolucaoResultadoNegativo,
                sprintf(
                    'Devolução #%d com resultado negativo de R$ %s.',
                    $devolucao->id,
                    $this->fmt($resultado),
                ),
                ['resultado' => $resultado],
            );
        }

        $venda = $devolucao->vendaOrigem;
        if ($venda !== null) {
            $linhaRentabilidade = $this->linhaRentabilidadeNoPeriodo($venda, $user, $periodo);
            if ($linhaRentabilidade !== null && (float) $linhaRentabilidade['resultado_liquido'] < 0) {
                $alertas[] = $this->montarAlerta(
                    $devolucao,
                    OlhoDeDeusAlertaTipo::RentabilidadeLojaNegativa,
                    sprintf(
                        'Após devolução #%d: rentabilidade do mês em R$ %s para %s / %s.',
                        $devolucao->id,
                        $this->fmt((float) $linhaRentabilidade['resultado_liquido']),
                        $linhaRentabilidade['cliente_nome'],
                        $linhaRentabilidade['unidade_origem_nome'],
                    ),
                    ['resultado_liquido_mes' => (float) $linhaRentabilidade['resultado_liquido']],
                );
            }
        }

        return $alertas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function detectarFrete(Movimentacao $movimentacao): array
    {
        $freteKg = (float) $movimentacao->valor_frete_kg;
        $limiteFrete = (float) config('olho_de_deus.frete_kg_maximo', 0.50);

        if ($freteKg <= $limiteFrete) {
            return [];
        }

        return [
            $this->montarAlerta(
                $movimentacao,
                OlhoDeDeusAlertaTipo::FreteKgElevado,
                sprintf(
                    'Movimentação #%d (%s): frete/kg R$ %s acima do limite.',
                    $movimentacao->id,
                    CategoriaMovimentacaoTipo::tryFrom((int) $movimentacao->categoria_movimentacao_id)?->nomeLegivel() ?? '—',
                    $this->fmt($freteKg),
                ),
                ['frete_kg' => $freteKg],
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function detectarPerdaOperacional(Movimentacao $movimentacao, OlhoDeDeusAlertaTipo $tipo): array
    {
        $valor = (float) $movimentacao->valor_total_movimentacao;
        $minimo = (float) config('olho_de_deus.perda_descarte_min_reais', 500);

        if ($valor < $minimo) {
            return [];
        }

        return [
            $this->montarAlerta(
                $movimentacao,
                $tipo,
                sprintf(
                    '%s #%d registrou R$ %s em %s (%s).',
                    $tipo === OlhoDeDeusAlertaTipo::DescartePerdaElevada ? 'Descarte' : 'Doação',
                    $movimentacao->id,
                    $this->fmt($valor),
                    $this->fmtKg((float) $movimentacao->qtd_fruta_kg),
                    $this->rotuloEmpresas($movimentacao),
                ),
                ['valor' => $valor, 'qtd_kg' => (float) $movimentacao->qtd_fruta_kg],
            ),
        ];
    }

    /**
     * @return list<Movimentacao>
     */
    private function movimentacoesRecentes(User $user, Carbon $since, Carbon $inicioPeriodo, Carbon $fimPeriodo): array
    {
        $limite = (int) config('olho_de_deus.max_movimentacoes_por_poll', 25);

        $query = $this->queryMovimentacoesNoPeriodo($user, $inicioPeriodo, $fimPeriodo)
            ->where(function (Builder $q) use ($since): void {
                $q->where('created_at', '>', $since)
                    ->orWhere('updated_at', '>', $since);
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limite);

        return $query->get()->all();
    }

    /**
     * @return list<Movimentacao>
     */
    private function movimentacoesNoPeriodo(User $user, Carbon $inicioPeriodo, Carbon $fimPeriodo): array
    {
        $limite = (int) config('olho_de_deus.max_movimentacoes_carga_inicial', 100);

        return $this->queryMovimentacoesNoPeriodo($user, $inicioPeriodo, $fimPeriodo)
            ->orderByDesc('data_movimentacao')
            ->orderByDesc('id')
            ->limit($limite)
            ->get()
            ->all();
    }

    /**
     * @return Builder<Movimentacao>
     */
    private function queryMovimentacoesNoPeriodo(User $user, Carbon $inicioPeriodo, Carbon $fimPeriodo): Builder
    {
        $empresaIds = $this->access->empresaIdsPermitidas($user);

        $query = Movimentacao::query()
            ->with([
                'fruta:id,nome',
                'empresaOrigem.entidade',
                'empresaDestino.entidade',
                'vendaOrigem.empresaOrigem.entidade',
                'vendaOrigem.empresaDestino.entidade',
            ])
            ->vigentesParaCalculo()
            ->whereBetween('data_movimentacao', [$inicioPeriodo, $fimPeriodo]);

        if ($empresaIds === null) {
            return $query;
        }

        if ($empresaIds === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $q) use ($empresaIds): void {
            $q->whereIn('id_empresa_origem', $empresaIds)
                ->orWhereHas('vendaOrigem', function (Builder $venda) use ($empresaIds): void {
                    $venda->whereIn('id_empresa_origem', $empresaIds);
                });
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    private function linhaRentabilidadeNoPeriodo(Movimentacao $venda, User $user, DashboardPeriodo $periodo): ?array
    {
        if ($venda->id_empresa_destino === null || $venda->id_empresa_origem === null) {
            return null;
        }

        $relatorio = $this->rentabilidadeLoja->gerar($user, [
            'data_inicio' => $periodo->inicio->toDateString(),
            'data_fim' => $periodo->fim->toDateString(),
            'id_empresa_origem' => (int) $venda->id_empresa_origem,
            'id_empresa_destino' => (int) $venda->id_empresa_destino,
            'agrupamento' => 'detalhe',
        ]);

        foreach ($relatorio['linhas'] as $linha) {
            if ((int) ($linha['fruta_id'] ?? 0) === (int) $venda->id_fruta) {
                return $linha;
            }
        }

        return null;
    }

    /**
     * @param  array<string, float|int>  $dados
     * @return array<string, mixed>
     */
    private function montarAlerta(
        Movimentacao $movimentacao,
        OlhoDeDeusAlertaTipo $tipo,
        string $mensagem,
        array $dados = [],
    ): array {
        $categoria = CategoriaMovimentacaoTipo::tryFrom((int) $movimentacao->categoria_movimentacao_id);

        return [
            'id' => $tipo->value.':'.$movimentacao->id,
            'tipo' => $tipo->value,
            'titulo' => $tipo->titulo(),
            'mensagem' => $mensagem,
            'severidade' => $tipo->severidade(),
            'movimentacao_id' => $movimentacao->id,
            'categoria' => $categoria?->nomeLegivel() ?? '—',
            'data_movimentacao' => $movimentacao->data_movimentacao instanceof Carbon
                ? $movimentacao->data_movimentacao->format('d/m/Y H:i')
                : '—',
            'url' => $this->urlMovimentacao($movimentacao, $categoria),
            'dados' => $dados,
            'detectado_em' => now()->toIso8601String(),
        ];
    }

    private function urlMovimentacao(Movimentacao $movimentacao, ?CategoriaMovimentacaoTipo $categoria): ?string
    {
        if ($categoria === null) {
            return null;
        }

        return match ($categoria) {
            CategoriaMovimentacaoTipo::Compra => route('admin.movimentacoes.compras.show', $movimentacao),
            CategoriaMovimentacaoTipo::Transferencia => route('admin.movimentacoes.transferencias.show', $movimentacao),
            CategoriaMovimentacaoTipo::Venda => route('admin.movimentacoes.vendas.show', $movimentacao),
            CategoriaMovimentacaoTipo::Doacao => route('admin.movimentacoes.doacoes.show', $movimentacao),
            CategoriaMovimentacaoTipo::Descarte => route('admin.movimentacoes.descartes.show', $movimentacao),
            CategoriaMovimentacaoTipo::Devolucao => route('admin.movimentacoes.devolucoes.show', $movimentacao),
            CategoriaMovimentacaoTipo::ConversaoEmbalagem => route('admin.movimentacoes.conversoes-embalagem.show', $movimentacao),
        };
    }

    private function rotuloEmpresas(Movimentacao $movimentacao): string
    {
        $origem = $movimentacao->empresaOrigem?->nomeExibicao() ?? '—';
        $destino = $movimentacao->empresaDestino?->nomeExibicao();

        if ($destino !== null && $destino !== '') {
            return $origem.' → '.$destino;
        }

        return $origem;
    }

    private function fmt(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }

    private function fmtKg(float $kg): string
    {
        return number_format($kg, 2, ',', '.').' kg';
    }
}
