@extends('layouts.app')

@section('title', 'Novo Grupo de Permissões')
@section('page-title', 'Novo Grupo de Permissões')

@section('content')
    <form method="POST" action="{{ route('admin.grupos-permissoes.store') }}">
        @csrf
        @include('admin.grupos-permissoes._form', [
            'submitLabel' => 'Salvar',
            'cardTitle' => 'Dados do grupo',
        ])
    </form>
@endsection
