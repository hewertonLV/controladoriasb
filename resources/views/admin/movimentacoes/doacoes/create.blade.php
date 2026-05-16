@extends('layouts.app')

@section('title', 'Nova doação')
@section('page-title', 'Movimentação — Nova doação')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <h4 class="header-title mb-0">Registrar doação</h4>
            @can('movimentacoes.doacoes.visualizar')
                <a href="{{ route('admin.movimentacoes.doacoes.index') }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
            @else
                <a href="{{ route('dashboard') }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
            @endcan
        </div>
        <div class="card-body">
            @include('admin.movimentacoes.doacoes._form-create', [
                'empresas_origem' => $empresas_origem,
                'empresas_destino_cliente' => $empresas_destino_cliente,
                'frutas' => $frutas,
            ])
        </div>
    </div>
@endsection

@push('scripts')
    @include('admin.movimentacoes.compras._masks')
@endpush
