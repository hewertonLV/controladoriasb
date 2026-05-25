@extends('layouts.app')

@section('title', 'Editar rota')
@section('page-title', 'Editar rota — '.$rota->nome)

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-body">
            <form method="post" action="{{ route('admin.captacao.rotas.update', $rota) }}" novalidate>
                @csrf
                @method('PUT')
                @include('admin.captacao.rotas._form', ['rota' => $rota])
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary">Salvar alterações</button>
                    <a href="{{ route('admin.captacao.rotas.index', ['carteira' => $rota->id_captacao_carteira]) }}" class="btn btn-light">Voltar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
