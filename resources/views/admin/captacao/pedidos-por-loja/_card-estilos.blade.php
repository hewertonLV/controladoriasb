<style>
    .captacao-loja-card {
        border: 3px solid #fff;
        border-radius: 0.5rem;
        transition: border-color 0.15s ease, box-shadow 0.15s ease;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        display: block;
        height: 100%;
    }
    .captacao-loja-card:hover {
        box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.08);
        color: inherit;
    }
    .captacao-loja-card.estado-em_andamento {
        border-color: #fd7e14;
    }
    .captacao-loja-card.estado-concluido {
        border-color: #198754;
    }
    .captacao-loja-card.estado-nao_iniciado {
        border-color: #dee2e6;
    }
    [data-bs-theme='dark'] .captacao-loja-card.estado-nao_iniciado {
        border-color: #495057;
    }
    .captacao-carteira-card {
        border: 1px solid var(--bs-border-color);
        border-radius: 0.5rem;
    }
</style>
