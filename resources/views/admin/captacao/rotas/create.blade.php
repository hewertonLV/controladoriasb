@extends('layouts.app')

@section('title', 'Nova rota')
@section('page-title', 'Nova rota')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-body">
            <form method="post" action="{{ route('admin.captacao.rotas.store') }}" novalidate>
                @csrf
                @include('admin.captacao.rotas._form', ['rota' => new \App\Models\Captacao\CaptacaoRota(['ativo' => true])])
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary">Salvar rota</button>
                    <a href="{{ route('admin.captacao.rotas.index', $carteiraId ? ['carteira' => $carteiraId] : []) }}" class="btn btn-light">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@include('admin.captacao._search-select-scripts')
