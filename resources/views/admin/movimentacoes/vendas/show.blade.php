@extends('layouts.app')

@section('title', 'Venda')
@section('page-title', 'Movimentação — Venda')

@section('content')
    @php
        use App\Enums\MovimentacaoStatusRegistro;

        $itensAtivos = $itens->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value);
        $saidaFisica = $movimentacao->unidadeEstoque;
        $saidaFisicaDiferente = $saidaFisica !== null
            && (int) $saidaFisica->id !== (int) $movimentacao->id_unidade_negocio_faturamento;

        $totalVendido = (float) $itensAtivos->sum('valor_nf_total');
        $totalCustoSaida = (float) $itensAtivos->sum('valor_custo_saida');
        $totalFrete = (float) $itensAtivos->sum('valor_frete_rateio');
        $totalResultado = (float) $itensAtivos->sum('resultado_movimentacao');
        $margemPct = $totalVendido > 0 ? round(($totalResultado / $totalVendido) * 100, 1) : 0.0;

        $statusBadgeClass = match ($movimentacao->status_registro) {
            MovimentacaoStatusRegistro::ATIVO->value => 'bg-success-subtle text-success',
            MovimentacaoStatusRegistro::CANCELADO->value => 'bg-danger-subtle text-danger',
            MovimentacaoStatusRegistro::SUBSTITUIDO->value => 'bg-warning-subtle text-warning',
            default => 'bg-secondary-subtle text-secondary',
        };

        $graficoItens = $itensAtivos
            ->map(fn ($item) => [
                'nome' => \Illuminate\Support\Str::limit($item->fruta?->nome ?? '—', 22),
                'resultado' => round((float) $item->resultado_movimentacao, 2),
                'vendido' => round((float) $item->valor_nf_total, 2),
            ])
            ->values()
            ->all();
    @endphp

    <x-admin.flash-messages />

    <div class="card venda-show-root mb-0">
        <div class="card-header d-flex align-items-center flex-wrap gap-2 py-3">
            <div class="me-auto">
                <h4 class="header-title mb-1">
                    Venda
                    @if ($movimentacao->vendaNota?->numero_nf)
                        <span class="text-primary">NF {{ $movimentacao->vendaNota->numero_nf }}</span>
                    @else
                        <span class="text-muted">#{{ $movimentacao->id }}</span>
                    @endif
                </h4>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge {{ $statusBadgeClass }}">{{ $movimentacao->status_registro }}</span>
                    <span class="text-muted small">
                        {{ $itensAtivos->count() }} {{ $itensAtivos->count() === 1 ? 'item ativo' : 'itens ativos' }}
                        · {{ $movimentacao->data_movimentacao?->format('d/m/Y H:i') ?? '—' }}
                    </span>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('admin.movimentacoes.vendas.index') }}" class="btn btn-light btn-sm">
                    <i class="ri-arrow-left-line me-1"></i> Voltar
                </a>
                @can('movimentacoes.vendas.editar')
                    <a href="{{ route('admin.movimentacoes.vendas.edit', $movimentacao) }}" class="btn btn-primary btn-sm">
                        <i class="ri-pencil-line me-1"></i> Corrigir
                    </a>
                @endcan
            </div>
        </div>

        <div class="card-body pt-3 pb-4">
            {{-- KPIs financeiros --}}
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="venda-show-kpi h-100">
                        <span class="venda-show-kpi__label">Valor vendido</span>
                        <span class="venda-show-kpi__value text-primary">R$ {{ number_format($totalVendido, 2, ',', '.') }}</span>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="venda-show-kpi h-100">
                        <span class="venda-show-kpi__label">Custo saída</span>
                        <span class="venda-show-kpi__value">R$ {{ number_format($totalCustoSaida, 2, ',', '.') }}</span>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="venda-show-kpi h-100">
                        <span class="venda-show-kpi__label">Frete rateio</span>
                        <span class="venda-show-kpi__value">R$ {{ number_format($totalFrete, 2, ',', '.') }}</span>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="venda-show-kpi h-100 {{ $totalResultado >= 0 ? 'venda-show-kpi--positivo' : 'venda-show-kpi--negativo' }}">
                        <span class="venda-show-kpi__label">Resultado</span>
                        <span class="venda-show-kpi__value">R$ {{ number_format($totalResultado, 2, ',', '.') }}</span>
                        @if ($totalVendido > 0)
                            <span class="venda-show-kpi__hint">{{ number_format($margemPct, 1, ',', '.') }}% sobre a venda</span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                {{-- Identificação e partes --}}
                <div class="col-lg-7">
                    <div class="venda-show-panel h-100">
                        <h6 class="venda-show-panel__title">Identificação e partes</h6>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="venda-show-field">
                                    <span class="venda-show-field__label">Nota fiscal</span>
                                    <span class="venda-show-field__value">{{ $movimentacao->vendaNota?->numero_nf ?? '—' }}</span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="venda-show-field">
                                    <span class="venda-show-field__label">Data da movimentação</span>
                                    <span class="venda-show-field__value">{{ $movimentacao->data_movimentacao?->format('d/m/Y H:i') ?? '—' }}</span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="venda-show-field">
                                    <span class="venda-show-field__label">Origem comercial</span>
                                    <span class="venda-show-field__value">{{ $movimentacao->empresaOrigem?->nomeExibicao() ?? '—' }}</span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="venda-show-field">
                                    <span class="venda-show-field__label">Cliente</span>
                                    <span class="venda-show-field__value">{{ $movimentacao->empresaDestino?->nomeExibicao() ?? '—' }}</span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="venda-show-field">
                                    <span class="venda-show-field__label">Faturamento</span>
                                    <span class="venda-show-field__value">{{ $movimentacao->unidadeFaturamento?->nome ?? '—' }}</span>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="venda-show-field">
                                    <span class="venda-show-field__label">Saída física (estoque)</span>
                                    <span class="venda-show-field__value">
                                        @if ($saidaFisica)
                                            {{ $saidaFisica->nome }}
                                            @if ($saidaFisica->is_hub)
                                                <span class="badge bg-info-subtle text-info ms-1">HUB</span>
                                            @endif
                                        @else
                                            <span class="text-muted">Mesma unidade do faturamento</span>
                                        @endif
                                    </span>
                                    @if ($saidaFisicaDiferente)
                                        <span class="venda-show-field__hint">Estoque debitado nesta unidade, não no faturamento.</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        @if ($saidaFisica?->is_hub && (float) $itensAtivos->sum(fn ($i) => (float) $i->valor_custo_operacional * (float) $i->qtd_fruta_kg) > 0)
                            <div class="alert alert-info py-2 px-3 mb-0 mt-3 small">
                                <i class="ri-information-line me-1"></i>
                                <strong>Saída HUB:</strong> o custo operacional de
                                <strong>{{ $movimentacao->unidadeFaturamento?->nome ?? '—' }}</strong>
                                está embutido no custo de saída de cada item.
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Gráfico --}}
                <div class="col-lg-5">
                    <div class="venda-show-panel h-100">
                        <h6 class="venda-show-panel__title">Resultado por item</h6>
                        @if ($graficoItens !== [])
                            <div id="venda-show-chart-itens" class="venda-show-chart venda-show-chart--itens"></div>
                        @else
                            <p class="text-muted small text-center mb-0 py-4">Sem itens ativos para exibir no gráfico.</p>
                        @endif
                    </div>
                </div>
            </div>

            <h5 class="mb-3 mt-1">Frutas vendidas nesta venda</h5>
            <div class="row g-3">
                @forelse ($itens as $item)
                    <div class="col-12 col-xl-6">
                        <div class="card border h-100 mb-0 venda-show-item-card">
                            <div class="card-body">
                                <div class="d-flex flex-wrap align-items-start gap-2 mb-3">
                                    <div class="me-auto">
                                        <h5 class="mb-1">{{ $item->fruta?->nome ?? '—' }}</h5>
                                        <div class="text-muted small">
                                            Movimentação #{{ $item->id }} · Versão {{ $item->versao }} · {{ $item->status_registro }}
                                        </div>
                                    </div>
                                    @if ($item->status_registro === MovimentacaoStatusRegistro::ATIVO->value)
                                        <div class="d-flex flex-wrap gap-1 justify-content-end">
                                            @can('movimentacoes.vendas.editar')
                                                <a href="{{ route('admin.movimentacoes.vendas.edit', $item) }}" class="btn btn-soft-primary btn-sm">
                                                    <i class="ri-pencil-line me-1"></i> Corrigir item
                                                </a>
                                            @endcan
                                            @can('movimentacoes.vendas.cancelar-admin')
                                                <form method="POST"
                                                      action="{{ route('admin.movimentacoes.vendas.cancelar-item-admin', $item) }}"
                                                      data-confirm="Cancelar apenas este item da venda?"
                                                      data-confirm-title="Cancelar item"
                                                      data-confirm-variant="danger"
                                                      data-confirm-btn="Cancelar item"
                                                      data-confirm-prompt="Informe o motivo do cancelamento"
                                                      data-confirm-prompt-field="motivo">
                                                    @csrf
                                                    <input type="hidden" name="motivo">
                                                    <button type="submit" class="btn btn-soft-danger btn-sm">
                                                        <i class="ri-close-circle-line me-1"></i> Cancelar item
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    @endif
                                </div>
                                <div class="row g-2 small">
                                    <div class="col-6 col-md-4">
                                        <span class="text-muted d-block">Qtd UM</span>
                                        <strong>{{ number_format((float) $item->qtd_fruta_um, 2, ',', '.') }} {{ $item->fruta?->unidade_medicao ?? '' }}</strong>
                                    </div>
                                    <div class="col-6 col-md-4">
                                        <span class="text-muted d-block">Qtd kg</span>
                                        <strong>{{ number_format((float) $item->qtd_fruta_kg, 2, ',', '.') }}</strong>
                                    </div>
                                    <div class="col-6 col-md-4">
                                        <span class="text-muted d-block">Preço médio kg</span>
                                        <strong>R$ {{ number_format((float) $item->preco_medio_fruta_kg, 2, ',', '.') }}</strong>
                                    </div>
                                    <div class="col-6 col-md-4">
                                        <span class="text-muted d-block">Valor vendido</span>
                                        <strong>R$ {{ number_format((float) $item->valor_nf_total, 2, ',', '.') }}</strong>
                                    </div>
                                    <div class="col-6 col-md-4">
                                        <span class="text-muted d-block">Custo saída</span>
                                        <strong>R$ {{ number_format((float) $item->valor_custo_saida, 2, ',', '.') }}</strong>
                                    </div>
                                    @if ((float) $item->valor_custo_operacional > 0 && $item->id_unidade_negocio_estoque && ($item->unidadeEstoque?->is_hub ?? false))
                                        @php
                                            $coTotalItem = round((float) $item->valor_custo_operacional * (float) $item->qtd_fruta_kg, 2);
                                        @endphp
                                        <div class="col-12">
                                            <div class="venda-show-co-box">
                                                <span class="text-muted d-block">Custo operacional (embutido no custo de saída)</span>
                                                <strong>R$ {{ number_format((float) $item->valor_custo_operacional, 2, ',', '.') }} / kg</strong>
                                                <span class="text-muted"> (total R$ {{ number_format($coTotalItem, 2, ',', '.') }})</span>
                                                <span class="text-muted d-block small">Faturamento: {{ $item->unidadeFaturamento?->nome ?? '—' }}</span>
                                            </div>
                                        </div>
                                    @endif
                                    @if ($item->observacao)
                                        <div class="col-12">
                                            <span class="text-muted d-block">Detalhamento do custo</span>
                                            <span class="small">{{ $item->observacao }}</span>
                                        </div>
                                    @endif
                                    <div class="col-6 col-md-4">
                                        <span class="text-muted d-block">Frete rateio</span>
                                        <strong>R$ {{ number_format((float) $item->valor_frete_rateio, 2, ',', '.') }}</strong>
                                    </div>
                                    <div class="col-6 col-md-4">
                                        <span class="text-muted d-block">Resultado</span>
                                        <strong @class(['text-success' => (float) $item->resultado_movimentacao >= 0, 'text-danger' => (float) $item->resultado_movimentacao < 0])>
                                            R$ {{ number_format((float) $item->resultado_movimentacao, 2, ',', '.') }}
                                        </strong>
                                    </div>
                                    <div class="col-12 col-md-8">
                                        <span class="text-muted d-block">Origem / Cliente</span>
                                        <strong>{{ $item->empresaOrigem?->nomeExibicao() ?? '—' }} → {{ $item->empresaDestino?->nomeExibicao() ?? '—' }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-warning mb-0">Nenhum item encontrado para esta venda.</div>
                    </div>
                @endforelse
            </div>

            @can('movimentacoes.vendas.cancelar-admin')
                @if ($movimentacao->status_registro === MovimentacaoStatusRegistro::ATIVO->value)
                    <hr class="my-4">
                    <div class="venda-show-panel">
                        <h6 class="venda-show-panel__title text-danger mb-3">Cancelamento administrativo</h6>
                        <form method="POST"
                              action="{{ route('admin.movimentacoes.vendas.cancelar-admin', $movimentacao) }}"
                              class="row g-2 align-items-end"
                              data-confirm="Cancelar esta venda completa e estornar todos os itens?"
                              data-confirm-title="Cancelar venda"
                              data-confirm-variant="danger"
                              data-confirm-btn="Cancelar venda">
                            @csrf
                            <div class="col-md-10">
                                <label class="form-label small text-muted mb-1" for="motivo-cancelamento-venda">Motivo</label>
                                <input id="motivo-cancelamento-venda" name="motivo" class="form-control" required placeholder="Descreva o motivo do cancelamento da venda completa">
                            </div>
                            <div class="col-md-2 d-grid">
                                <button class="btn btn-danger" type="submit">Cancelar venda completa</button>
                            </div>
                        </form>
                    </div>
                @endif
            @endcan
        </div>
    </div>
@endsection

@push('head')
    <style>
        .venda-show-root .venda-show-kpi {
            border: 1px solid var(--bs-border-color);
            border-radius: 0.5rem;
            padding: 0.85rem 1rem;
            background: var(--bs-body-bg);
        }
        .venda-show-root .venda-show-kpi__label {
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--bs-secondary-color);
            margin-bottom: 0.25rem;
        }
        .venda-show-root .venda-show-kpi__value {
            display: block;
            font-size: 1.15rem;
            font-weight: 600;
            line-height: 1.2;
        }
        .venda-show-root .venda-show-kpi__hint {
            display: block;
            font-size: 0.75rem;
            color: var(--bs-secondary-color);
            margin-top: 0.2rem;
        }
        .venda-show-root .venda-show-kpi--positivo {
            border-color: rgba(var(--bs-success-rgb), 0.35);
            background: rgba(var(--bs-success-rgb), 0.06);
        }
        .venda-show-root .venda-show-kpi--negativo {
            border-color: rgba(var(--bs-danger-rgb), 0.35);
            background: rgba(var(--bs-danger-rgb), 0.06);
        }
        .venda-show-root .venda-show-panel {
            border: 1px solid var(--bs-border-color);
            border-radius: 0.5rem;
            padding: 1rem 1.15rem;
            background: var(--bs-light-bg-subtle, var(--bs-tertiary-bg));
        }
        html[data-bs-theme="dark"] .venda-show-root .venda-show-panel {
            background: rgba(var(--bs-light-rgb), 0.03);
        }
        .venda-show-root .venda-show-panel__title {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--bs-secondary-color);
            margin-bottom: 0.85rem;
        }
        .venda-show-root .venda-show-panel__subtitle {
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            color: var(--bs-secondary-color);
        }
        .venda-show-root .venda-show-field__label {
            display: block;
            font-size: 0.75rem;
            color: var(--bs-secondary-color);
            margin-bottom: 0.15rem;
        }
        .venda-show-root .venda-show-field__value {
            display: block;
            font-weight: 600;
            line-height: 1.35;
        }
        .venda-show-root .venda-show-field__hint {
            display: block;
            font-size: 0.75rem;
            color: var(--bs-secondary-color);
            margin-top: 0.15rem;
        }
        .venda-show-root .venda-show-chart--itens {
            min-height: 220px;
        }
        .venda-show-root .venda-show-co-box {
            background: rgba(var(--bs-info-rgb), 0.08);
            border: 1px solid rgba(var(--bs-info-rgb), 0.2);
            border-radius: 0.375rem;
            padding: 0.5rem 0.65rem;
        }
        .venda-show-root .venda-show-item-card {
            transition: box-shadow 0.15s ease;
        }
        .venda-show-root .venda-show-item-card:hover {
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.06);
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendor/apexcharts/apexcharts.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof ApexCharts === 'undefined') {
                return;
            }

            const fmtBrl = (valor) => new Intl.NumberFormat('pt-BR', {
                style: 'currency',
                currency: 'BRL',
            }).format(valor);

            const itens = @json($graficoItens);
            const itensEl = document.querySelector('#venda-show-chart-itens');
            if (itensEl && itens.length > 0) {
                new ApexCharts(itensEl, {
                    chart: {
                        type: 'bar',
                        height: Math.max(160, itens.length * 36),
                        fontFamily: 'inherit',
                        toolbar: { show: false },
                    },
                    series: [{
                        name: 'Resultado',
                        data: itens.map((item) => item.resultado),
                    }],
                    plotOptions: {
                        bar: {
                            horizontal: true,
                            borderRadius: 4,
                            barHeight: '68%',
                            colors: {
                                ranges: [
                                    { from: -999999999, to: -0.01, color: '#fa5c7c' },
                                    { from: 0, to: 999999999, color: '#0acf97' },
                                ],
                            },
                        },
                    },
                    dataLabels: {
                        enabled: true,
                        formatter: (val) => fmtBrl(val),
                        style: { fontSize: '11px' },
                    },
                    xaxis: {
                        categories: itens.map((item) => item.nome),
                        labels: {
                            formatter: (val) => fmtBrl(Number(val)),
                            style: { fontSize: '11px' },
                        },
                    },
                    yaxis: {
                        labels: { style: { fontSize: '11px' } },
                    },
                    grid: {
                        borderColor: 'rgba(150,150,150,0.15)',
                        padding: { left: 8, right: 16 },
                    },
                    tooltip: {
                        y: {
                            formatter: (val) => fmtBrl(val),
                        },
                    },
                }).render();
            }
        });
    </script>
@endpush
