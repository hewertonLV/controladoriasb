@extends('layouts.app')

@section('title', 'Nova carteira')
@section('page-title', 'Nova carteira de captação')

@section('content')
    <x-admin.flash-messages />
    <div class="card">
        <div class="card-body">
            <form method="post" action="{{ route('admin.captacao.carteiras.store') }}">
                @csrf
                @include('admin.captacao.carteiras._form')
                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <a href="{{ route('admin.captacao.carteiras.index') }}" class="btn btn-light">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
@endsection

@include('admin.captacao._search-select-scripts')
