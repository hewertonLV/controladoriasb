@extends('layouts.app')

@section('title', 'Editar Grupo de Contrato')
@section('page-title', 'Editar Grupo de Contrato')

@section('content')
    <x-admin.flash-messages />

    <form method="POST" action="{{ route('admin.grupos-contrato.update', $grupoContrato) }}">
        @csrf
        @method('PUT')
        @include('admin.grupos-contrato._form', [
            'grupoContrato' => $grupoContrato,
            'cardTitle' => 'Editar grupo de contrato',
            'submitLabel' => 'Salvar alterações',
        ])
    </form>
@endsection
