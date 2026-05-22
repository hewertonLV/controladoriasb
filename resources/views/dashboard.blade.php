@extends('layouts.app')

@section('title', 'Dashboard financeira')
@section('page-title', 'Dashboard')

@section('content')
    @php
        $cards = [
            ['key' => 'faturado', 'label' => 'Total faturado', 'icone' => 'ri-money-dollar-circle-line', 'cor' => 'primary'],
            ['key' => 'devolucao', 'label' => 'Total devoluções', 'icone' => 'ri-arrow-go-back-line', 'cor' => 'warning'],
            ['key' => 'liquido', 'label' => 'Total líquido', 'icone' => 'ri-scales-3-line', 'cor' => 'info'],
            ['key' => 'rentabilidade', 'label' => 'Total rentabilidade', 'icone' => 'ri-line-chart-line', 'cor' => 'success'],
            ['key' => 'descartado', 'label' => 'Fruta descartada', 'icone' => 'ri-delete-bin-line', 'cor' => 'danger'],
            ['key' => 'doado', 'label' => 'Total doado', 'icone' => 'ri-gift-line', 'cor' => 'secondary'],
        ];
    @endphp

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end mb-3">
                @include('layouts.partials.dashboard-filtro-periodo', [
                    'mesAtual' => $mesAtual,
                    'diaAtual' => $diaAtual,
                    'periodoTipoInicial' => $periodoTipoInicial,
                ])
            </div>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <label class="form-label fw-semibold mb-0">Unidades de negócio</label>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-muted" id="dashboard-filtro-status"></span>
                    <span class="badge bg-secondary-subtle text-secondary" id="dashboard-monitor-status">
                        <i class="ri-loader-4-line me-1"></i> Iniciando…
                    </span>
                    <button type="button" class="btn btn-sm btn-light" id="dashboard-pausar" title="Pausar atualização">
                        <i class="ri-pause-line"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-light d-none" id="dashboard-retomar" title="Retomar">
                        <i class="ri-play-line"></i>
                    </button>
                </div>
            </div>

            @if (count($financeiro['unidades_disponiveis']) === 0)
                <p class="text-muted mb-0">Nenhuma unidade disponível para o seu usuário.</p>
            @else
                <div class="d-flex flex-wrap gap-3" id="dashboard-unidades-switches">
                    @foreach ($financeiro['unidades_disponiveis'] as $unidade)
                        <div class="form-check form-switch mb-0">
                            <input type="checkbox"
                                   class="form-check-input dashboard-unidade-switch"
                                   role="switch"
                                   id="dashboard-unidade-{{ $unidade['id'] }}"
                                   value="{{ $unidade['id'] }}"
                                   @checked(in_array($unidade['id'], $financeiro['filtro_unidades'], true))>
                            <label class="form-check-label" for="dashboard-unidade-{{ $unidade['id'] }}">
                                {{ $unidade['nome'] }}
                            </label>
                        </div>
                    @endforeach
                </div>
                <p class="text-muted small mb-0 mt-2">
                    Desative uma unidade para removê-la dos totais. Os dados atualizam em tempo real enquanto esta página estiver aberta e visível.
                </p>
            @endif

            <p class="text-muted small mb-0 mt-2">
                <i class="ri-calendar-line me-1"></i>
                Período: <span id="dashboard-periodo-label">{{ $financeiro['periodo']['label'] }}</span>
            </p>
        </div>
    </div>

    <div class="row row-cols-xxl-3 row-cols-lg-2 row-cols-1 g-3 mb-4" id="dashboard-cards">
        @foreach ($cards as $card)
            @php
                $dados = $financeiro['cards'][$card['key']];
            @endphp
            <div class="col">
                <div class="card h-100 mb-0">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 justify-content-between">
                            <div>
                                <h5 class="text-muted fs-13 fw-bold text-uppercase mb-2">{{ $card['label'] }}</h5>
                                <h3 class="my-1 fw-bold dashboard-card-reais {{ $dados['reais'] > 0 ? 'text-success' : ($dados['reais'] < 0 ? 'text-danger' : 'text-muted') }}"
                                    data-card="{{ $card['key'] }}"
                                    data-metric="reais">
                                    R$ {{ number_format($dados['reais'], 2, ',', '.') }}
                                </h3>
                                <p class="mb-0 text-muted">
                                    <span class="text-nowrap dashboard-card-kg" data-card="{{ $card['key'] }}" data-metric="kg">
                                        {{ number_format($dados['kg'], 2, ',', '.') }} kg
                                    </span>
                                    @if ($card['key'] === 'rentabilidade')
                                        <span class="mx-1">·</span>
                                        <span class="text-nowrap fw-semibold dashboard-card-pct"
                                              data-card="{{ $card['key'] }}"
                                              data-metric="percentual">
                                            @if ($dados['percentual'] === null)
                                                —
                                            @else
                                                {{ number_format($dados['percentual'], 2, ',', '.') }}%
                                            @endif
                                        </span>
                                    @endif
                                </p>
                            </div>
                            <div class="avatar-xl flex-shrink-0">
                                <span class="avatar-title bg-{{ $card['cor'] }}-subtle text-{{ $card['cor'] }} rounded-circle fs-42">
                                    <i class="{{ $card['icone'] }}"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="header-title mb-0">Movimentação diária do mês</h4>
                    <p class="text-muted mb-0 small">Faturado, doado e descartado (R$) e volume vendido (kg), do dia 01 até hoje.</p>
                </div>
                <div class="card-body px-2 pt-0 position-relative">
                    <div id="dashboard-financeiro-diario" class="apex-charts" data-colors="#0acf97,#777edd,#fa5c7c,#45bbe0"></div>
                    <div id="dashboard-chart-loading-diario" class="dashboard-chart-loading d-none">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="header-title mb-0">Rentabilidade</h4>
                    <p class="text-muted mb-0 small">Composição do resultado (vendas + devoluções) no período.</p>
                </div>
                <div class="card-body px-2 pt-0 position-relative">
                    <div id="dashboard-financeiro-pizza" class="apex-charts" data-colors="#0acf97,#fa5c7c,#777edd"></div>
                    <div id="dashboard-chart-loading-pizza" class="dashboard-chart-loading d-none">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="header-title mb-0">Rentabilidade por unidade de negócio</h4>
                    <p class="text-muted mb-0 small">Resultado (R$) e margem (%) de cada unidade ativa no filtro.</p>
                </div>
                <div class="card-body px-2 pt-0 position-relative">
                    <div id="dashboard-financeiro-rentabilidade-unidades" class="apex-charts" data-colors="#0acf97,#777edd"></div>
                    <div id="dashboard-chart-loading-rentabilidade-unidades" class="dashboard-chart-loading d-none">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .dashboard-chart-loading {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.65);
            z-index: 2;
        }
        html[data-bs-theme="dark"] .dashboard-chart-loading {
            background: rgba(0, 0, 0, 0.35);
        }
        #dashboard-cards.opacity-50 {
            opacity: 0.55;
            transition: opacity 0.2s ease;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendor/apexcharts/apexcharts.min.js') }}"></script>
    <script>
        window.dashboardFinanceiro = @json($financeiro);
        window.dashboardFinanceiroConfig = {
            dadosUrl: @json($dadosUrl),
            pollIntervalMs: @json($pollIntervalMs ?? config('dashboard_financeiro.poll_interval_ms', 45000)),
        };
    </script>
    <script src="{{ asset('assets/js/pages/dashboard-financeiro.js') }}"></script>
@endpush
