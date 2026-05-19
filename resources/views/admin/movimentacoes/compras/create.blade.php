@extends('layouts.app')

@section('title', 'Nova compra')
@section('page-title', 'Movimentação — Nova compra')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <h4 class="header-title mb-0">Registrar compra</h4>
            @can('movimentacoes.compras.visualizar')
                <a href="{{ route('admin.movimentacoes.compras.index') }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
            @else
                <a href="{{ route('dashboard') }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
            @endcan
        </div>
        <div class="card-body">
            @include('admin.movimentacoes.compras._form-create', [
                'empresas_origem' => $empresas_origem,
                'empresas_destino' => $empresas_destino,
                'frutas' => $frutas,
                'fretes' => $fretes,
            ])
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials.admin.movimentacoes-form-scripts')
@endpush
