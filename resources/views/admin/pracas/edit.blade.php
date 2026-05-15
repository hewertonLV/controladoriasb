@extends('layouts.app')

@section('title', 'Editar Praça')
@section('page-title', 'Editar Praça')

@section('content')
    <form method="POST" action="{{ route('admin.pracas.update', $praca) }}">
        @csrf
        @method('PUT')
        @include('admin.pracas._form', [
            'submitLabel' => 'Atualizar',
            'cardTitle' => 'Editar praça',
        ])
    </form>
@endsection
