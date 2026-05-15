@extends('layouts.app')

@section('title', 'Nova Praça')
@section('page-title', 'Nova Praça')

@section('content')
    <form method="POST" action="{{ route('admin.pracas.store') }}">
        @csrf
        @include('admin.pracas._form', [
            'submitLabel' => 'Salvar',
            'cardTitle' => 'Nova praça',
        ])
    </form>
@endsection
