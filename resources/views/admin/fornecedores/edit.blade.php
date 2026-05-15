@extends('layouts.app')

@section('title', 'Editar Fornecedor')
@section('page-title', 'Editar Fornecedor')

@section('content')
    <form method="POST" action="{{ route('admin.fornecedores.update', $fornecedor) }}">
        @csrf
        @method('PUT')
        @include('admin.fornecedores._form', [
            'submitLabel' => 'Atualizar',
            'cardTitle' => 'Editar fornecedor',
            'estados' => $estados,
            'fornecedor' => $fornecedor,
        ])
    </form>
@endsection
