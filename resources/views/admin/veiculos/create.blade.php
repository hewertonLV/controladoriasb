@extends('layouts.app')

@section('title', 'Novo Veículo')
@section('page-title', 'Novo Veículo')

@section('content')
    <form method="POST" action="{{ route('admin.veiculos.store') }}">
        @csrf
        @include('admin.veiculos._form', [
            'submitLabel' => 'Salvar',
            'cardTitle' => 'Novo veículo',
        ])
    </form>
@endsection

