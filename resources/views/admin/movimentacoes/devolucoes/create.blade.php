@extends('layouts.app')

@section('title', 'Nova devolução')
@section('page-title', 'Movimentação — Nova devolução')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <h4 class="header-title mb-0">Registrar devolução</h4>
            <a href="{{ route('admin.movimentacoes.devolucoes.index') }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
        </div>
        <div class="card-body">
            @include('admin.movimentacoes.devolucoes._form-create')
        </div>
    </div>
@endsection
