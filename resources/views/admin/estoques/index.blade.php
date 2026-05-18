@extends('layouts.app')

@section('title', 'Estoques')
@section('page-title', 'Estoques')

@section('content')
    <x-admin.flash-messages />

    <div class="card mb-3">
        <div class="card-header">
            <h4 class="header-title mb-0">Selecione uma unidade de negócio</h4>
            <p class="text-muted mb-0">Clique em uma unidade para abrir uma nova tela com as frutas daquele estoque.</p>
        </div>
    </div>

    <div class="row g-3">
        @foreach ($unidadesCards as $unidadeCard)
            <div class="col-12 col-md-6 col-xl-3">
                <a href="{{ route('admin.estoques.unidade', $unidadeCard) }}"
                   class="card h-100 text-decoration-none border border-light-subtle">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-2">
                            <span class="avatar-sm d-inline-flex align-items-center justify-content-center rounded bg-primary-subtle text-primary">
                                <i class="ri-building-2-line fs-20"></i>
                            </span>
                            <div class="min-w-0">
                                <h5 class="mb-1 text-body text-truncate" title="{{ $unidadeCard->nome }}">{{ $unidadeCard->nome }}</h5>
                                <div class="small text-muted">CIGAM {{ $unidadeCard->id_cigam ?: '—' }}</div>
                            </div>
                        </div>
                        <div class="row g-2 mt-3 small">
                            <div class="col-4">
                                <div class="text-muted">Frutas</div>
                                <div class="fw-semibold text-body">{{ (int) $unidadeCard->posicoes_count }}</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted">Kg</div>
                                <div class="fw-semibold text-body">{{ number_format((float) $unidadeCard->total_kg, 2, ',', '.') }}</div>
                            </div>
                            <div class="col-4">
                                <div class="text-muted">Valor</div>
                                <div class="fw-semibold text-body">R$ {{ number_format((float) $unidadeCard->valor_total, 2, ',', '.') }}</div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endsection
