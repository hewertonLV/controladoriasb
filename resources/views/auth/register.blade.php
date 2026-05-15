@extends('layouts.guest')

@section('title', 'Cadastro')

@section('content')
    <h4 class="fw-semibold mb-2 fs-18">Criar nova conta</h4>
    <p class="text-muted mb-4">Preencha os dados abaixo para criar sua conta.</p>

    <form method="POST" action="{{ route('register') }}" class="text-start mb-3">
        @csrf

        <div class="mb-3">
            <label class="form-label" for="name">Nome</label>
            <input type="text" id="name" name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                   class="form-control @error('name') is-invalid @enderror" placeholder="Seu nome">
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label class="form-label" for="email">E-mail</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="username"
                   class="form-control @error('email') is-invalid @enderror" placeholder="seu@email.com">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label class="form-label" for="password">Senha</label>
            <input type="password" id="password" name="password" required autocomplete="new-password"
                   class="form-control @error('password') is-invalid @enderror" placeholder="Mínimo 8 caracteres">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label class="form-label" for="password_confirmation">Confirmar senha</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                   class="form-control @error('password_confirmation') is-invalid @enderror" placeholder="Repita a senha">
            @error('password_confirmation')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-grid">
            <button class="btn btn-primary fw-semibold" type="submit">Cadastrar</button>
        </div>
    </form>

    <p class="text-muted fs-14 mb-4">
        Já tem uma conta?
        <a href="{{ route('login') }}" class="fw-semibold text-danger ms-1">Entrar</a>
    </p>
@endsection
