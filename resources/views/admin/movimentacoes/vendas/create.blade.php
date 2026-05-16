@extends('layouts.app')

@section('title', 'Nova venda')
@section('page-title', 'Movimentação — Nova venda')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <h4 class="header-title mb-0">Registrar venda</h4>
            <a href="{{ route('admin.movimentacoes.vendas.index') }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
        </div>
        <div class="card-body">
            @include('admin.movimentacoes.vendas._form-create')
        </div>
    </div>
@endsection

@push('scripts')
    @include('admin.movimentacoes.compras._masks')
@endpush
