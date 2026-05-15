@extends('layouts.app')

@section('title', 'Novo Fornecedor')
@section('page-title', 'Novo Fornecedor')

@section('content')
    <form method="POST" action="{{ route('admin.fornecedores.store') }}">
        @csrf
        @include('admin.fornecedores._form', [
            'submitLabel' => 'Salvar',
            'cardTitle' => 'Novo fornecedor',
            'estados' => $estados,
            'fornecedor' => $fornecedor,
        ])
    </form>
@endsection
