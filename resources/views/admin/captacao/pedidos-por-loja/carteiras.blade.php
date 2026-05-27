@extends('layouts.app')

@section('title', 'Captação por loja — carteiras')
@section('page-title', 'Captação por loja')

@section('content')
    @include('admin.captacao.pedidos-por-loja._card-estilos')

    <div class="page-container">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <p class="text-muted mb-0">Escolha a carteira para captar pedidos loja a loja.</p>
            <form method="get" class="d-flex gap-2 align-items-center">
                <label class="form-label mb-0 small" for="data_referencia">Data</label>
                <input type="date" name="data_referencia" id="data_referencia" class="form-control form-control-sm"
                       value="{{ $dataReferencia }}">
                <button type="submit" class="btn btn-sm btn-primary">Filtrar</button>
            </form>
        </div>

        @if ($resumos->isEmpty())
            <div class="alert alert-info">
                Nenhum lote em captação para esta data. Abra a captação em
                <a href="{{ route('admin.captacao.lotes.index') }}">Lotes</a>.
            </div>
        @else
            <div class="row g-3">
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
    </div>
@endsection
