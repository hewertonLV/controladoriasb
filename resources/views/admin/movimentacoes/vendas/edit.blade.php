@extends('layouts.app')

@section('title', 'Corrigir venda')
@section('page-title', 'Movimentação — Corrigir venda')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <h4 class="header-title mb-0">Corrigir venda #{{ $movimentacao->id }}</h4>
            <a href="{{ route('admin.movimentacoes.vendas.show', $movimentacao) }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
        </div>
        <div class="card-body">
            @include('admin.movimentacoes.vendas._form-create', ['movimentacao' => $movimentacao, 'opcoes' => $opcoes])
        </div>
    </div>
@endsection

@push('scripts')
    @include('admin.movimentacoes.compras._masks')
@endpush
