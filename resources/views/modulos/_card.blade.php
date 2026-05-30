@php
    $modulo = $card->modulo;
@endphp

<div class="col">
    <a href="{{ route('modulos.entrar', $modulo) }}"
       class="card modulos-card h-100 text-decoration-none border shadow-none hover-shadow">
        <div class="card-body d-flex flex-column align-items-center text-center p-4">
            <div class="modulos-card-icon bg-{{ $modulo->corBootstrap() }}-subtle text-{{ $modulo->corBootstrap() }} rounded-circle d-flex align-items-center justify-content-center mb-3">
                <i class="{{ $modulo->icone() }} fs-28"></i>
            </div>
            <h4 class="card-title fw-bold text-body mb-2">{{ $modulo->label() }}</h4>
            <p class="card-text text-muted mb-0 flex-grow-1">{{ $modulo->descricao() }}</p>
            <span class="btn btn-sm btn-{{ $modulo->corBootstrap() }} mt-4">
                Entrar
                <i class="ri-arrow-right-line ms-1"></i>
            </span>
        </div>
    </a>
</div>

<style>
    .modulos-card {
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .modulos-card:hover {
        transform: translateY(-2px);
    }

    .modulos-card-icon {
        width: 72px;
        height: 72px;
    }

    .hover-shadow:hover {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.08) !important;
    }
</style>
