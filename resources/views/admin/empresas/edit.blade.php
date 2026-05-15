@extends('layouts.app')

@section('title', 'Editar Empresa')
@section('page-title', 'Editar Empresa')

@section('content')
    <form method="POST" action="{{ route('admin.empresas.update', $empresa) }}">
        @csrf
        @method('PUT')
        @include('admin.empresas._form', [
            'submitLabel' => 'Salvar alterações',
        ])
    </form>
@endsection
