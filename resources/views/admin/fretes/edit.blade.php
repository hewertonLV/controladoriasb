@extends('layouts.app')

@section('title', 'Editar Frete')
@section('page-title', 'Editar Frete')

@section('content')
    <form method="POST" action="{{ route('admin.fretes.update', $frete) }}">
        @csrf
        @method('PUT')
        @include('admin.fretes._form', [
            'submitLabel' => 'Atualizar',
            'cardTitle' => 'Editar frete',
        ])
    </form>
@endsection
