@extends('layouts.app')

@section('title', 'Corrigir devolução')
@section('page-title', 'Movimentação — Corrigir devolução')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <h4 class="header-title mb-0">Corrigir devolução #{{ $movimentacao->id }}</h4>
            <a href="{{ route('admin.movimentacoes.devolucoes.show', $movimentacao) }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
        </div>
        <div class="card-body">
            @include('admin.movimentacoes.devolucoes._form-create', ['movimentacao' => $movimentacao, 'opcoes' => $opcoes])
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials.admin.movimentacoes-form-scripts')
@endpush
