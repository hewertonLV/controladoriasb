@extends('layouts.app')

@section('title', 'Editar Cliente')
@section('page-title', 'Editar Cliente')

@section('content')
    <form method="POST" action="{{ route('admin.clientes.update', $cliente) }}">
        @csrf
        @method('PUT')
        @include('admin.clientes._form', [
            'submitLabel' => 'Atualizar',
            'cardTitle' => 'Editar cliente',
        ])
    </form>
@endsection

