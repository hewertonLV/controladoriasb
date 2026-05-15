@extends('layouts.guest')

@section('title', 'Confirmar senha')

@section('content')
    <h4 class="fw-semibold mb-2 fs-18">Confirme sua senha</h4>
    <p class="text-muted mb-4">
        Esta é uma área protegida do sistema. Por favor, confirme sua senha antes de continuar.
    </p>

    <form method="POST" action="{{ route('password.confirm') }}" class="text-start mb-3">
        @csrf

        <div class="mb-3">
            <label class="form-label" for="password">Senha</label>
            <input type="password" id="password" name="password" required autocomplete="current-password" autofocus
                   class="form-control @error('password') is-invalid @enderror">
            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="d-grid">
            <button class="btn btn-primary fw-semibold" type="submit">Confirmar</button>
        </div>
    </form>
@endsection
