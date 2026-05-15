@extends('layouts.app')

@section('title', 'Nova Empresa')
@section('page-title', 'Nova Empresa')

@section('content')
    <form method="POST" action="{{ route('admin.empresas.store') }}">
        @csrf
        @include('admin.empresas._form', [
            'submitLabel' => 'Cadastrar empresa',
        ])
    </form>
@endsection
