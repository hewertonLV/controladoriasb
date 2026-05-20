@extends('layouts.guest')

@section('title', 'Página não encontrada')

@section('content')
    @php
        $homeUrl = auth()->check() ? route('dashboard') : route('login');
        $homeLabel = auth()->check() ? 'Voltar ao início' : 'Ir para o login';
    @endphp

    <div class="mb-3">
        <span class="display-1 fw-bold text-primary lh-1">404</span>
    </div>

    <h4 class="fw-semibold mb-2 fs-18">Página não encontrada</h4>
    <p class="text-muted mb-4">
        O endereço <code class="text-break">{{ request()->path() }}</code> não existe ou foi movido.
        Verifique o link ou retorne à tela inicial.
    </p>

    <div class="d-grid gap-2">
        <a href="{{ $homeUrl }}" class="btn btn-primary fw-semibold">
            <i class="ri-home-4-line me-1"></i> {{ $homeLabel }}
        </a>
        @auth
            <a href="javascript:history.back()" class="btn btn-light">
                <i class="ri-arrow-left-line me-1"></i> Voltar
            </a>
        @endauth
    </div>
@endsection
