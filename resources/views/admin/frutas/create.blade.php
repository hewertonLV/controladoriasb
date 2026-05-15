@extends('layouts.app')

@section('title', 'Nova Fruta')
@section('page-title', 'Nova Fruta')

@section('content')
    <form method="POST" action="{{ route('admin.frutas.store') }}">
        @csrf
        @include('admin.frutas._form', [
            'submitLabel' => 'Salvar',
            'cardTitle' => 'Nova fruta',
        ])
    </form>
@endsection
