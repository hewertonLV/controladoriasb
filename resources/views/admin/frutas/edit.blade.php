@extends('layouts.app')

@section('title', 'Editar Fruta')
@section('page-title', 'Editar Fruta')

@section('content')
    <form method="POST" action="{{ route('admin.frutas.update', $fruta) }}">
        @csrf
        @method('PUT')
        @include('admin.frutas._form', [
            'submitLabel' => 'Atualizar',
            'cardTitle' => 'Editar fruta',
        ])
    </form>
@endsection
