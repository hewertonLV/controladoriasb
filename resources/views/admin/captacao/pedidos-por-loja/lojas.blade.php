@extends('layouts.app')

@section('title', 'Lojas — '.$lote->carteira?->nome)
@section('page-title', 'Captação por loja — '.$lote->carteira?->nome)

@section('content')
    @include('admin.captacao.pedidos-por-loja._card-estilos')

    <div class="page-container">
        <div class="mb-3">
            <a href="{{ route('admin.captacao.pedidos-por-loja.carteiras', ['data_referencia' => $lote->data_referencia->format('Y-m-d')]) }}"
               class="btn btn-sm btn-light"><i class="ri-arrow-left-line"></i> Carteiras</a>
            <a href="{{ route('admin.captacao.matriz.index', ['lote' => $lote->id]) }}"
               class="btn btn-sm btn-outline-secondary ms-1">Matriz</a>
        </div>

        @if ($lojas->isEmpty())
            <div class="alert alert-warning">
                Nenhuma loja vinculada a esta carteira.
                <a href="{{ route('admin.captacao.carteiras.edit', $lote->carteira) }}">Edite a carteira</a>
                para incluir lojas.
            </div>
        @endif

        <div class="row g-3">
            @foreach ($lojas as $entrada)
                @php
                    $cliente = $entrada['cliente'];
                    $estado = $entrada['estado'];
                @endphp
                <div class="col-sm-6 col-md-4 col-lg-3">
                    <a href="{{ route('admin.captacao.pedidos-por-loja.show', [$lote, $cliente]) }}"
                       class="card captacao-loja-card estado-{{ $estado['estado'] }} shadow-sm">
                        <div class="card-body p-3">
                            <div class="fw-semibold text-truncate" title="{{ $cliente->fantasia ?: $cliente->razao_social }}">
                                {{ $cliente->fantasia ?: $cliente->razao_social }}
                            </div>
                            @if ($estado['estado'] === 'concluido')
                                @if ($estado['rentabilidade']['margem_percentual'] !== null)
                                    <div class="small text-success mt-2">
                                        Rent. {{ $estado['rentabilidade']['margem_percentual'] }}%
                                        · R$ {{ number_format((float) $estado['rentabilidade']['margem_total'], 2, ',', '.') }}
                                    </div>
                                @else
                                    <div class="small text-success mt-2">Concluído</div>
                                @endif
                            @elseif ($estado['estado'] === 'em_andamento')
                                <div class="small text-warning mt-2">Em andamento</div>
                            @else
                                <div class="small text-muted mt-2">Não iniciado</div>
                            @endif
                            @if (! $entrada['possui_frutas'])
                                <div class="small text-danger mt-1">Sem frutas vinculadas</div>
                            @endif
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
@endsection
