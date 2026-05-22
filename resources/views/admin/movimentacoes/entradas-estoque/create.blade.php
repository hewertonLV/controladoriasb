@extends('layouts.app')

@section('title', 'Nova entrada de estoque')
@section('page-title', 'Movimentação — Nova entrada de estoque')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <h4 class="header-title mb-0">Registrar entrada da produção</h4>
            @can('movimentacoes.entradas-estoque.visualizar')
                <a href="{{ route('admin.movimentacoes.entradas-estoque.index') }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
            @endcan
        </div>
        <div class="card-body">
            @include('admin.movimentacoes.entradas-estoque._form-create', [
                'empresas_unidade' => $empresas_unidade,
                'frutas' => $frutas,
            ])
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials.admin.movimentacoes-form-scripts')
@endpush
