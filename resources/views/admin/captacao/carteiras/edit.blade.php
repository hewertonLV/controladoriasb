@extends('layouts.app')

@section('title', 'Editar carteira')
@section('page-title', 'Editar carteira — '.$carteira->nome)

@section('content')
    <x-admin.flash-messages />
    <div class="card">
        <div class="card-body">
            <form method="post" action="{{ route('admin.captacao.carteiras.update', $carteira) }}">
                @csrf
                @method('PUT')
                @include('admin.captacao.carteiras._form', ['carteira' => $carteira])
                @include('admin.captacao.carteiras._lojas', [
                    'carteira' => $carteira,
                    'lojasVinculadas' => $lojasVinculadas,
                    'lojasSemCarteira' => $lojasSemCarteira,
                ])
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <a href="{{ route('admin.captacao.carteiras.index') }}" class="btn btn-light">Voltar</a>
                </div>
            </form>
        </div>
    </div>
@endsection
