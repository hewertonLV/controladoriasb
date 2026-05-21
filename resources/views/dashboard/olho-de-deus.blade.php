@extends('layouts.app')

@section('title', 'Olho de Deus')
@section('page-title', 'Olho de Deus')

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                @include('layouts.partials.dashboard-filtro-mes', [
                    'mesAtual' => $mesAtual,
                    'inputId' => 'olho-de-deus-mes',
                    'botaoId' => 'olho-de-deus-buscar',
                ])
            </div>
            <p class="text-muted small mb-0 mt-2">
                Período: <span id="olho-de-deus-periodo-label">dia 01 do mês selecionado até hoje (mês atual) ou até o último dia (meses anteriores)</span>
            </p>
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <p class="text-muted mb-0">
                Monitoramento em tempo real de cenários de prejuízo nas suas unidades.
                A consulta só ocorre enquanto esta página estiver aberta e visível.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-secondary-subtle text-secondary" id="olho-de-deus-status">
                <i class="ri-pause-circle-line me-1"></i> Aguardando
            </span>
            <button type="button" class="btn btn-sm btn-light" id="olho-de-deus-pausar" title="Pausar atualização">
                <i class="ri-pause-line"></i>
            </button>
            <button type="button" class="btn btn-sm btn-light d-none" id="olho-de-deus-retomar" title="Retomar">
                <i class="ri-play-line"></i>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card mb-0">
                <div class="card-body py-3">
                    <div class="text-muted fs-12 text-uppercase fw-semibold">Alertas na tela</div>
                    <h3 class="mb-0 fw-bold" id="olho-de-deus-total">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card mb-0">
                <div class="card-body py-3">
                    <div class="text-muted fs-12 text-uppercase fw-semibold mb-1">Critérios monitorados</div>
                    <div class="d-flex flex-wrap gap-1">
                        <span class="badge bg-danger-subtle text-danger">Preço venda &lt; custo</span>
                        <span class="badge bg-danger-subtle text-danger">Frete/kg &gt; R$ 0,50</span>
                        <span class="badge bg-danger-subtle text-danger">Rentabilidade negativa</span>
                        <span class="badge bg-danger-subtle text-danger">Rentabilidade loja no mês &lt; 0</span>
                        <span class="badge bg-warning-subtle text-warning">Descarte/doação elevados</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="header-title mb-0">Alertas</h4>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="olho-de-deus-limpar">
                Limpar lista
            </button>
        </div>
        <div class="card-body p-0">
            <div id="olho-de-deus-vazio" class="text-center text-muted py-5">
                <i class="ri-eye-line fs-36 d-block mb-2"></i>
                Nenhum alerta no momento. Novos eventos aparecerão aqui automaticamente.
            </div>
            <div class="list-group list-group-flush d-none" id="olho-de-deus-lista"></div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        window.__OLHO_DE_DEUS__ = {
            pollUrl: @json($pollUrl),
            pollIntervalMs: @json($pollIntervalMs),
        };
    </script>
    <script src="{{ asset('assets/js/pages/olho-de-deus.js') }}"></script>
@endpush
