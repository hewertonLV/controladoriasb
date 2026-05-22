@extends('layouts.app')

@section('title', 'Calendário de Fretes')
@section('page-title', 'Calendário de Fretes')

@section('content')
    <x-admin.flash-messages />

    <div class="row">
        <div class="col-xl-3">
            <div class="card">
                <div class="card-body">
                    <div class="mb-3">
                        <label for="fretes-calendario-mes" class="form-label">Mês</label>
                        <input class="form-control"
                               id="fretes-calendario-mes"
                               type="month"
                               name="mes"
                               value="{{ $mesAtual }}">
                    </div>
                    <button type="button" class="btn btn-primary w-100 mb-3" id="fretes-calendario-buscar">
                        <i class="ri-search-line me-1"></i> Buscar
                    </button>

                    @can('fretes.criar')
                        <a href="{{ route('admin.fretes.create') }}" class="btn btn-soft-primary w-100 mb-3">
                            <i class="ri-add-line me-1"></i> Novo frete
                        </a>
                    @endcan

                    <p class="text-muted small mb-2">Legenda</p>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="badge bg-success-subtle text-success">Aberta</span>
                        <span class="text-muted small">frete em aberto</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <span class="badge bg-secondary-subtle text-secondary">Encerrada</span>
                        <span class="text-muted small">frete encerrado</span>
                    </div>

                    <p class="text-muted small mb-1">
                        Período: <span id="fretes-calendario-periodo-label" class="fw-semibold">—</span>
                    </p>
                    <p class="text-muted small mb-0">
                        Total no mês: <span id="fretes-calendario-total" class="fw-semibold">0</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-xl-9">
            <div class="card">
                <div class="card-body position-relative">
                    <div id="fretes-calendario-loading"
                         class="position-absolute top-0 start-0 w-100 h-100 d-none align-items-center justify-content-center bg-body bg-opacity-75"
                         style="z-index: 2;">
                        <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
                    </div>
                    <div id="fretes-calendario"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="frete-calendario-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title" id="frete-calendario-modal-titulo">Frete</h4>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Situação</dt>
                        <dd class="col-sm-8" id="frete-calendario-modal-situacao">—</dd>

                        <dt class="col-sm-4">Valor</dt>
                        <dd class="col-sm-8" id="frete-calendario-modal-valor">—</dd>

                        <dt class="col-sm-4">Fruta/kg</dt>
                        <dd class="col-sm-8" id="frete-calendario-modal-fruta-kg">—</dd>

                        <dt class="col-sm-4">Veículo</dt>
                        <dd class="col-sm-8" id="frete-calendario-modal-veiculo">—</dd>

                        <dt class="col-sm-4">Cadastro</dt>
                        <dd class="col-sm-8" id="frete-calendario-modal-criado">—</dd>

                        <dt class="col-sm-4">Descrição</dt>
                        <dd class="col-sm-8" id="frete-calendario-modal-descricao">—</dd>
                    </dl>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Fechar</button>
                    <a href="#" class="btn btn-primary d-none" id="frete-calendario-modal-editar">
                        <i class="ri-pencil-line me-1"></i> Editar
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendor/fullcalendar/index.global.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/fullcalendar/locales/pt-br.global.min.js') }}"></script>
    <script>
        window.__FRETES_CALENDARIO__ = {
            eventosUrl: @json($eventosUrl),
            mesInicial: @json($mesAtual),
        };
    </script>
    <script src="{{ asset('assets/js/pages/fretes-calendario.js') }}"></script>
@endpush
