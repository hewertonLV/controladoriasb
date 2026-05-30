@extends('layouts.app')

@section('title', 'Demanda de venda')
@section('page-title', 'Demanda de venda — captação')

@section('content')
    @include('admin.captacao.pedidos-por-loja._card-estilos')

    <div class="page-container">
        <div class="mb-3">
            <a href="{{ $urlVoltar }}" class="btn btn-sm btn-light">
                <i class="ri-arrow-left-line"></i> Vendas
            </a>
        </div>

        @include('admin.movimentacoes._demandas-captacao-detalhe', ['card' => $card])
    </div>

    @include('admin.captacao.matriz._modal-demanda-faltas-estoque')
@endsection

@include('admin.movimentacoes._demandas-captacao-scripts')
