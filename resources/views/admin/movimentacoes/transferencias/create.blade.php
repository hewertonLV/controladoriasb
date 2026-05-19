@extends('layouts.app')

@section('title', 'Nova transferência')
@section('page-title', 'Movimentação — Nova transferência')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <h4 class="header-title mb-0">Registrar transferência</h4>
            @can('movimentacoes.transferencias.visualizar')
                <a href="{{ route('admin.movimentacoes.transferencias.index') }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
            @else
                <a href="{{ route('dashboard') }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
            @endcan
        </div>
        <div class="card-body">
            @include('admin.movimentacoes.transferencias._form-create', [
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
