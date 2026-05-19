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

        <x-password-input
            id="password"
            name="password"
            label="Senha"
            :required="true"
            autocomplete="current-password"
            placeholder="Sua senha"
        />

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="form-check">
                <input type="checkbox"
                       class="form-check-input"
                       id="remember_me"
                       name="remember"
                       value="1"
                       @checked(old('remember'))>
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
@endsection
