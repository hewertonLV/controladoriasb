@extends('layouts.guest')

@section('title', 'Trocar Senha')

@section('content')
    <h4 class="fw-semibold mb-2 fs-18">Definir nova senha</h4>
    <p class="text-muted mb-4">
        Por questões de segurança, você precisa definir uma nova senha antes de continuar.
        A senha padrão não pode ser mantida.
    </p>

    @if (session('warning'))
        <div class="alert alert-warning text-start" role="alert">
            <i class="ri-error-warning-line me-1"></i> {{ session('warning') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.force.update') }}" class="text-start mb-3">
        @csrf
        @method('PUT')

        <x-password-input
            id="password"
            name="password"
            label='Nova senha <span class="text-danger">*</span>'
            :required="true"
            autofocus
            autocomplete="new-password"
            placeholder="Mínimo 8 caracteres"
        />

        <x-password-input
            id="password_confirmation"
            name="password_confirmation"
            label='Confirmar nova senha <span class="text-danger">*</span>'
            :required="true"
            autocomplete="new-password"
            placeholder="Repita a nova senha"
        />

        <div class="d-grid gap-2">
            <button class="btn btn-primary fw-semibold" type="submit">
                <i class="ri-shield-check-line me-1"></i> Definir nova senha
            </button>
        </div>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="d-grid">
        @csrf
        <button type="submit" class="btn btn-link text-muted">
            <i class="ri-logout-box-line me-1"></i> Sair
        </button>
    </form>
@endsection
