@extends('layouts.app')

@section('title', 'Dashboard financeira')
@section('page-title', 'Dashboard')

@section('content')
    @php
        $fmtReais = static fn (float $v): string => 'R$ '.number_format($v, 2, ',', '.');
        $fmtKg = static fn (float $v): string => number_format($v, 2, ',', '.').' kg';
        $fmtPercentual = static function (?float $v): string {
            if ($v === null) {
                return '—';
            }

            return number_format($v, 2, ',', '.').'%';
        };
        $clsValor = static function (float $v): string {
            if ($v > 0) {
                return 'text-success';
            }
            if ($v < 0) {
                return 'text-danger';
            }

            return 'text-muted';
        };

        $cards = [
            [
                'key' => 'faturado',
                'label' => 'Total faturado',
                'icone' => 'ri-money-dollar-circle-line',
                'cor' => 'primary',
            ],
            [
                'key' => 'devolucao',
                'label' => 'Total devoluções',
                'icone' => 'ri-arrow-go-back-line',
                'cor' => 'warning',
            ],
            [
                'key' => 'liquido',
                'label' => 'Total líquido',
                'icone' => 'ri-scales-3-line',
                'cor' => 'info',
            ],
            [
                'key' => 'rentabilidade',
                'label' => 'Total rentabilidade',
                'icone' => 'ri-line-chart-line',
                'cor' => 'success',
            ],
            [
                'key' => 'descartado',
                'label' => 'Fruta descartada',
                'icone' => 'ri-delete-bin-line',
                'cor' => 'danger',
            ],
            [
                'key' => 'doado',
                'label' => 'Total doado',
                'icone' => 'ri-gift-line',
                'cor' => 'secondary',
            ],
        ];
    @endphp

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('dashboard') }}" class="row g-3 align-items-end">
                <div class="col-lg-8">
                    <label for="unidades" class="form-label">Unidades de negócio</label>
                    <select name="unidades[]" id="unidades" class="form-select" multiple size="4">
                        @foreach ($financeiro['unidades_disponiveis'] as $unidade)
                            <option value="{{ $unidade['id'] }}"
                                @selected(in_array($unidade['id'], $financeiro['filtro_unidades'], true))>
                                {{ $unidade['nome'] }}
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">
                        Nenhuma selecionada = todas as unidades permitidas ao seu usuário.
                        Segure Ctrl (ou Cmd) para selecionar mais de uma.
                    </div>
                </div>
                <div class="col-lg-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="ri-filter-3-line me-1"></i> Aplicar filtro
                    </button>
                    <a href="{{ route('dashboard') }}" class="btn btn-light w-100 mt-2">Limpar filtro</a>
                </div>
            </form>
            <p class="text-muted small mb-0 mt-2">
                <i class="ri-calendar-line me-1"></i>
                Período: {{ $financeiro['periodo']['label'] }}
            </p>
        </div>
    </div>

    <div class="row row-cols-xxl-3 row-cols-lg-2 row-cols-1 g-3 mb-4">
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
                                <h3 class="my-1 fw-bold {{ $clsValor($dados['reais']) }}">{{ $fmtReais($dados['reais']) }}</h3>
                                <p class="mb-0 text-muted">
                                    <span class="text-nowrap">{{ $fmtKg($dados['kg']) }}</span>
                                    @if ($card['key'] === 'rentabilidade')
                                        <span class="mx-1">·</span>
                                        <span class="text-nowrap fw-semibold {{ $clsValor($dados['reais']) }}">
                                            {{ $fmtPercentual($dados['percentual'] ?? null) }}
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
                <div class="card-body px-2 pt-0">
                    <div id="dashboard-financeiro-diario" class="apex-charts" data-colors="#0acf97,#777edd,#fa5c7c,#45bbe0"></div>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="header-title mb-0">Rentabilidade</h4>
                    <p class="text-muted mb-0 small">Composição do resultado (vendas + devoluções) no período.</p>
                </div>
                <div class="card-body px-2 pt-0">
                    <div id="dashboard-financeiro-pizza" class="apex-charts" data-colors="#0acf97,#fa5c7c,#777edd"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendor/apexcharts/apexcharts.min.js') }}"></script>
    <script>
        window.dashboardFinanceiro = @json($financeiro);
    </script>
    <script src="{{ asset('assets/js/pages/dashboard-financeiro.js') }}"></script>
@endpush
