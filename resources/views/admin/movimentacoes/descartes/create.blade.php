@extends('layouts.app')

@section('title', 'Novo descarte')
@section('page-title', 'Movimentação — Novo descarte')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <h4 class="header-title mb-0">Registrar descarte</h4>
            @can('movimentacoes.descartes.visualizar')
                <a href="{{ route('admin.movimentacoes.descartes.index') }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
            @else
                <a href="{{ route('dashboard') }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
            @endcan
        </div>
        <div class="card-body">
            @include('admin.movimentacoes.descartes._form-create', [
                'empresas_origem' => $empresas_origem,
                'frutas' => $frutas,
                'categorias_descarte' => $categorias_descarte,
            ])
        </div>
    </div>
@endsection

@push('scripts')
    @include('admin.movimentacoes.compras._masks')
@endpush
