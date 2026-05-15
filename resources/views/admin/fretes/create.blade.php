@extends('layouts.app')

@section('title', 'Novo Frete')
@section('page-title', 'Novo Frete')

@section('content')
    <form method="POST" action="{{ route('admin.fretes.store') }}">
        @csrf
        @include('admin.fretes._form', [
            'submitLabel' => 'Salvar',
            'cardTitle' => 'Novo frete',
        ])
    </form>
@endsection
