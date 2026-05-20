@extends('layouts.app')

@section('title', 'Novo Grupo de Contrato')
@section('page-title', 'Novo Grupo de Contrato')

@section('content')
    <x-admin.flash-messages />

    <form method="POST" action="{{ route('admin.grupos-contrato.store') }}">
        @csrf
        @include('admin.grupos-contrato._form', [
            'grupoContrato' => $grupoContrato,
            'cardTitle' => 'Cadastrar grupo de contrato',
            'submitLabel' => 'Cadastrar',
        ])
    </form>
@endsection
