@extends('layouts.app')

@section('title', 'Novo Usuário')
@section('page-title', 'Novo Usuário')

@section('content')
    <div class="alert alert-info d-flex align-items-center" role="alert">
        <i class="ri-information-line fs-22 me-2"></i>
        <div>
            O usuário será criado com a senha padrão <strong>sitiosbs</strong>.
            No primeiro login, ele será obrigado a definir uma nova senha antes de acessar o sistema.
        </div>
    </div>

    <form method="POST" action="{{ route('admin.usuarios.store') }}">
        @csrf
        @include('admin.usuarios._form', [
            'submitLabel' => 'Criar usuário',
            'cardTitle' => 'Dados do usuário',
        ])
    </form>
@endsection
