@extends('layouts.app')

@php
    use App\Enums\AppModulo;

    $moduloCaptacaoAtivo = AppModulo::tryFromSession() === AppModulo::Captacao;
@endphp

@section('title', 'Captação por loja — carteiras')
@section('page-title', 'Captação por loja')

@section('content')
    @include('admin.captacao.pedidos-por-loja._card-estilos')

    <x-admin.flash-messages />

    <div class="page-container">
        @unless ($moduloCaptacaoAtivo)
            @include('admin.captacao._abrir-captacao-form', [
                'carteiras' => $carteiras,
                'dataReferencia' => $dataReferencia,
            ])
        @endunless

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <p class="text-muted mb-0">Escolha a carteira para captar pedidos loja a loja.</p>
            <form method="get" class="d-flex gap-2 align-items-center">
                <label class="form-label mb-0 small" for="data_referencia">Data</label>
                <input type="date" name="data_referencia" id="data_referencia" class="form-control form-control-sm"
                       value="{{ $dataReferencia }}">
                <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
            </form>
        </div>

        @if ($resumos->isEmpty() && ($resumosConcluidos ?? collect())->isEmpty())
            <div class="alert alert-info mb-0">
                Nenhum lote em captação para esta data.
                @if ($moduloCaptacaoAtivo)
                    Clique em <strong>Criar Captação</strong> no topo da página para abrir uma nova captação.
                @else
                    Use o formulário acima para abrir uma nova captação.
                @endif
            </div>
        @else
            @if ($resumos->isNotEmpty())
                <h6 class="text-muted mb-2">Em andamento</h6>
                <div class="row g-3 mb-4">
                    @foreach ($resumos as $resumo)
                        @php $lote = $resumo['lote']; @endphp
                        <div class="col-md-6 col-lg-4">
                            <a href="{{ route('admin.captacao.pedidos-por-loja.lojas', $lote) }}"
                               class="card captacao-carteira-card h-100 shadow-sm text-decoration-none text-body">
                                <div class="card-body">
                                    <h5 class="card-title mb-1">{{ $lote->carteira?->nome ?? 'Carteira' }}</h5>
                                    <p class="text-muted small mb-2">
                                        {{ $lote->unidadeGalpao?->nome }} · {{ $lote->unidadeFaturamento?->nome }}
                                    </p>
                                    <p class="mb-0">
                                        <span class="badge bg-success-subtle text-success">
                                            {{ $resumo['concluidas'] }}/{{ $resumo['total_lojas'] }} lojas concluídas
                                        </span>
                                    </p>
                                </div>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif

            @if (($resumosConcluidos ?? collect())->isNotEmpty())
                <h6 class="text-muted mb-2">Captação concluída</h6>
                <div class="row g-3">
                    @foreach ($resumosConcluidos as $resumo)
                        @php $lote = $resumo['lote']; @endphp
                        <div class="col-md-6 col-lg-4">
                            <div class="card captacao-carteira-card h-100 shadow-sm border-success-subtle">
                                <div class="card-body">
                                    <h5 class="card-title mb-1">{{ $lote->carteira?->nome ?? 'Carteira' }}</h5>
                                    <p class="text-muted small mb-2">
                                        {{ $lote->unidadeGalpao?->nome }} · {{ $lote->unidadeFaturamento?->nome }}
                                    </p>
                                    <p class="mb-2">
                                        <span class="badge bg-success">
                                            <i class="ri-check-double-line"></i> Captação concluída
                                        </span>
                                    </p>
                                    <p class="small text-muted mb-2">
                                        {{ $resumo['concluidas'] }}/{{ $resumo['total_lojas'] }} lojas finalizadas.
                                        Não é possível reabrir a captação deste lote.
                                    </p>
                                    <a href="{{ route('admin.captacao.pedidos-por-loja.lojas', $lote) }}"
                                       class="btn btn-sm btn-outline-secondary">
                                        Ver lojas (somente leitura)
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
@endsection

@if (! $moduloCaptacaoAtivo)
    @include('admin.captacao._search-select-scripts')
@endif
