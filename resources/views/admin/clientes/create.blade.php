@extends('layouts.app')

@section('title', 'Novo Cliente')
@section('page-title', 'Novo Cliente')

@section('content')
    <form method="POST" action="{{ route('admin.clientes.store') }}">
        @csrf
        @include('admin.clientes._form', [
            'submitLabel' => 'Salvar',
            'cardTitle' => 'Novo cliente',
        ])
    </form>
@endsection

