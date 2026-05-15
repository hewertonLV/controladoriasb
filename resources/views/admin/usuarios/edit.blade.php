@extends('layouts.app')

@section('title', 'Editar Usuário')
@section('page-title', 'Editar Usuário')

@section('content')
    @if ($isProtected)
        <div class="alert alert-warning d-flex align-items-center" role="alert">
            <i class="ri-shield-keyhole-line fs-22 me-2"></i>
            <div>
                Este é o usuário <strong>Programador</strong> do sistema. A role
                <strong>Programador</strong> não pode ser removida e a senha não pode ser resetada
                por esta tela.
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.usuarios.update', $user) }}">
        @csrf
        @method('PUT')
        @include('admin.usuarios._form', [
            'submitLabel' => 'Salvar alterações',
            'cardTitle' => 'Dados do usuário',
        ])
    </form>
@endsection
