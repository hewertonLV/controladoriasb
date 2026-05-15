@extends('layouts.guest')

@section('title', 'Recuperar senha')

@section('content')
    <h4 class="fw-semibold mb-2 fs-18">Esqueceu sua senha?</h4>
    <p class="text-muted mb-4">
        Informe seu e-mail e enviaremos um link para você redefinir a senha.
    </p>

    @if (session('status'))
        <div class="alert alert-success" role="alert">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="text-start mb-3">
        @csrf

        <div class="mb-3">
            <label class="form-label" for="email">E-mail</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                   class="form-control @error('email') is-invalid @enderror" placeholder="seu@email.com">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-grid">
            <button class="btn btn-primary fw-semibold" type="submit">Enviar link de recuperação</button>
        </div>
    </form>

    <p class="text-muted fs-14 mb-4">
        <a href="{{ route('login') }}" class="fw-semibold text-danger ms-1">Voltar para o login</a>
    </p>
@endsection
