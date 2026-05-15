@extends('layouts.app')

@section('title', 'Editar Grupo de Permissões')
@section('page-title', 'Editar Grupo de Permissões')

@section('content')
    @if ($isProgramador)
        <div class="alert alert-warning d-flex align-items-center" role="alert">
            <i class="ri-shield-keyhole-line fs-22 me-2"></i>
            <div>
                <strong>Grupo Programador:</strong> não pode ser alterado.
                O acesso total é garantido pelo sistema (<code>Gate::before</code>) independentemente das permissões marcadas aqui.
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.grupos-permissoes.update', $role) }}">
        @csrf
        @method('PUT')
        @include('admin.grupos-permissoes._form', [
            'submitLabel' => 'Salvar alterações',
            'cardTitle' => 'Dados do grupo',
        ])
    </form>
@endsection
