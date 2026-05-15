@extends('layouts.app')

@section('title', 'Editar grupo')
@section('page-title', 'Editar grupo')

@section('content')
    <x-admin.flash-messages />

    <form method="POST" action="{{ route('admin.grupos.update', $grupo) }}">
        @csrf
        @method('PUT')
        @include('admin.grupos._form', [
            'cardTitle' => 'Editar grupo',
            'submitLabel' => 'Atualizar',
        ])
    </form>
@endsection
