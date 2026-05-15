@extends('layouts.app')

@section('title', 'Novo grupo')
@section('page-title', 'Novo grupo')

@section('content')
    <x-admin.flash-messages />

    <form method="POST" action="{{ route('admin.grupos.store') }}">
        @csrf
        @include('admin.grupos._form', [
            'cardTitle' => 'Novo grupo',
            'submitLabel' => 'Cadastrar',
        ])
    </form>
@endsection
