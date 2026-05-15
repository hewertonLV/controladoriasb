@extends('layouts.guest')

@section('title', 'Nova senha')

@section('content')
    <h4 class="fw-semibold mb-2 fs-18">Redefinir senha</h4>
    <p class="text-muted mb-4">Crie uma nova senha para sua conta.</p>

    <form method="POST" action="{{ route('password.store') }}" class="text-start mb-3">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="mb-3">
            <label class="form-label" for="email">E-mail</label>
            <input type="email" id="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username"
                   class="form-control @error('email') is-invalid @enderror">
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label class="form-label" for="password">Nova senha</label>
            <input type="password" id="password" name="password" required autocomplete="new-password"
                   class="form-control @error('password') is-invalid @enderror">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="mb-3">
            <label class="form-label" for="password_confirmation">Confirmar nova senha</label>
            <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                   class="form-control @error('password_confirmation') is-invalid @enderror">
            @error('password_confirmation')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-grid">
            <button class="btn btn-primary fw-semibold" type="submit">Redefinir senha</button>
        </div>
    </form>
@endsection
