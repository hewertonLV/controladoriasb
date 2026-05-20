@extends('layouts.app')

@section('title', 'Editar estado')
@section('page-title', 'Editar estado')

@section('content')
    <x-admin.flash-messages />

    @if ($estado->trashed())
        <div class="alert alert-warning">
            Este estado está <strong>inativo</strong>. Reative-o para voltar a aparecer nos cadastros de fornecedores e unidades.
        </div>
    @endif

    <form method="POST" action="{{ route('admin.estados.update', $estado) }}">
        @csrf
        @method('PUT')
        @include('admin.estados._form', [
            'estado' => $estado,
            'cardTitle' => 'Editar estado',
            'submitLabel' => 'Salvar alterações',
        ])
    </form>
@endsection
