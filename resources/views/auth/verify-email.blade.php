@extends('layouts.guest')

@section('title', 'Verificar e-mail')

@section('content')
    <h4 class="fw-semibold mb-2 fs-18">Verifique seu e-mail</h4>
    <p class="text-muted mb-4">
        Obrigado por se cadastrar! Antes de continuar, verifique seu e-mail clicando no link que acabamos de enviar.
        Se você não recebeu o e-mail, podemos enviar outro.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="alert alert-success" role="alert">
            Um novo link de verificação foi enviado para o e-mail informado no seu cadastro.
        </div>
    @endif

    <div class="d-flex justify-content-between gap-2 mb-3">
        <form method="POST" action="{{ route('verification.send') }}" class="d-grid flex-grow-1">
            @csrf
            <button type="submit" class="btn btn-primary fw-semibold">Reenviar e-mail</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="d-grid flex-grow-1">
            @csrf
            <button type="submit" class="btn btn-light fw-semibold">Sair</button>
        </form>
    </div>
@endsection
