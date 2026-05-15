@extends('layouts.app')

@section('title', 'Editar Unidade de Negócio')
@section('page-title', 'Editar Unidade de Negócio')

@section('content')
    <form method="POST" action="{{ route('admin.unidades-negocio.update', $unidadeNegocio) }}">
        @csrf
        @method('PUT')
        @include('admin.unidades-negocio._form', [
            'submitLabel' => 'Atualizar',
            'cardTitle' => 'Editar unidade',
        ])
    </form>

    @include('admin.unidades-negocio._historico_custo_operacional', [
        'unidadeNegocio' => $unidadeNegocio,
    ])
@endsection
