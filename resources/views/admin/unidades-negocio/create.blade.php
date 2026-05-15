@extends('layouts.app')

@section('title', 'Nova Unidade de Negócio')
@section('page-title', 'Nova Unidade de Negócio')

@section('content')
    <form method="POST" action="{{ route('admin.unidades-negocio.store') }}">
        @csrf
        @include('admin.unidades-negocio._form', [
            'submitLabel' => 'Salvar',
            'cardTitle' => 'Nova unidade',
        ])
    </form>
@endsection
