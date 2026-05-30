@extends('layouts.app')

@section('title', 'Lote de captação')
@section('page-title', 'Lote de captação #'.$lote->id)

@section('content')
    <x-admin.flash-messages />

    @include('admin.captacao._lote-timeline-status', ['lote' => $lote])

    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <strong>Captação — lojas, quantidades e preços</strong>
            <a href="{{ route('admin.captacao.matriz.index', ['lote' => $lote->id]) }}"
               class="btn btn-sm btn-outline-secondary">
                Abrir matriz
            </a>
        </div>
        <div class="card-body">
            @include('admin.captacao._captacao-matriz-leitura', [
                'lote' => $lote,
                'clientes' => $clientes,
                'frutas' => $frutas,
                'frutasPorCliente' => $frutasPorCliente,
                'pedidosPorCliente' => $pedidosPorCliente,
            ])
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <strong>Romaneio de carregamento</strong>
        </div>
        <div class="card-body table-responsive">
            @include('admin.captacao._romaneio-carregamento-por-rota', [
                'romaneiosPorRota' => $romaneiosCarregamentoPorRota,
                'lote' => $lote,
                'idPrefixo' => 'lote-romaneio',
            ])
        </div>
    </div>
@endsection
