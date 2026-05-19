@extends('layouts.app')

@section('title', 'Novo estado')
@section('page-title', 'Novo estado')

@section('content')
    <x-admin.flash-messages />

    <form method="POST" action="{{ route('admin.estados.store') }}">
        @csrf
        @include('admin.estados._form', [
            'cardTitle' => 'Novo estado',
            'submitLabel' => 'Cadastrar',
        ])
    </form>
@endsection
