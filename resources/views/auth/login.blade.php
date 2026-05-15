@extends('layouts.guest')

@section('title', 'Entrar')

@section('content')
    <h4 class="fw-semibold mb-2 fs-18">Acesse sua conta</h4>
    <p class="text-muted mb-4">Informe seu e-mail e senha para acessar o painel.</p>

    @if (session('status'))
        <div class="alert alert-success" role="alert">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="text-start mb-3">
        @csrf

        <div class="mb-3">
            <label class="form-label" for="email">E-mail</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="form-control @error('email') is-invalid @enderror" placeholder="seu@email.com">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label class="form-label" for="password">Senha</label>
            <input type="password" id="password" name="password" required autocomplete="current-password"
                   class="form-control @error('password') is-invalid @enderror" placeholder="Sua senha">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-flex justify-content-between mb-3">
            <div class="form-check">
                <input type="checkbox" class="form-check-input" id="remember_me" name="remember">
                <label class="form-check-label" for="remember_me">Lembrar-me</label>
            </div>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-muted border-bottom border-dashed">Esqueceu a senha?</a>
            @endif
        </div>

        <div class="d-grid">
            <button class="btn btn-primary fw-semibold" type="submit">Entrar</button>
        </div>
    </form>

    <p class="text-muted fs-14 mb-4">
        Não tem uma conta?
        <a href="{{ route('register') }}" class="fw-semibold text-danger ms-1">Cadastre-se</a>
    </p>
@endsection
