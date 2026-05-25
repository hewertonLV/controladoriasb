@extends('layouts.app')

@section('title', 'Detalhe — frutas da loja')
@section('page-title', 'Detalhe — '.($cliente->fantasia ?: $cliente->razao_social))

@section('content')
    <x-admin.flash-messages />

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-6">
                    <p class="mb-1"><strong>Faturamento:</strong> {{ $cliente->unidadeNegocio?->nome ?? '—' }}</p>
                    @if ($cliente->captacaoCarteira)
                        <p class="mb-0"><strong>Carteira:</strong> {{ $cliente->captacaoCarteira->nome }}</p>
                    @endif
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="{{ route('admin.captacao.frutas-por-loja.index', ['faturamento' => $cliente->id_unidade_negocio]) }}" class="btn btn-sm btn-light">
                        <i class="ri-arrow-left-line"></i> Voltar à lista de lojas
                    </a>
                </div>
            </div>
        </div>
    </div>

    @unless ($podeSalvarVinculos ?? false)
        <div class="alert alert-info">
            Você pode consultar os vínculos, mas não tem permissão para alterá-los. Solicite <code>captacao.cliente_fruta.vincular</code> ou <code>captacao.pedido.editar</code> ao administrador.
        </div>
    @else
        <div class="alert alert-secondary py-2">
            Marque as frutas que esta loja compra e clique em <strong>Salvar vínculos</strong>. Essas frutas entram como colunas na matriz de captação.
        </div>
    @endunless

    <form method="post" action="{{ route('admin.captacao.clientes.frutas.sync', $cliente) }}">
        @csrf
        @method('PUT')

        <div class="card">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <strong>Vincular frutas a esta loja</strong>
                <span class="badge bg-primary">{{ count($vinculadas) }} selecionada(s)</span>
                @if ($podeSalvarVinculos ?? false)
                    <input type="search" id="filtro-frutas" class="form-control form-control-sm ms-md-auto" style="max-width: 280px;" placeholder="Buscar fruta...">
                @endif
            </div>
            <div class="card-body">
                <div class="row g-2" id="lista-frutas">
                    @foreach ($frutas as $fruta)
                        <div class="col-md-4 col-lg-3 fruta-item" data-nome="{{ mb_strtolower($fruta->nome) }}">
                            <div class="form-check">
                                <input type="checkbox"
                                       class="form-check-input"
                                       name="id_frutas[]"
                                       value="{{ $fruta->id }}"
                                       id="fruta_{{ $fruta->id }}"
                                       @checked(in_array($fruta->id, $vinculadas, true))
                                       @disabled(! ($podeSalvarVinculos ?? false))>
                                <label class="form-check-label" for="fruta_{{ $fruta->id }}">{{ $fruta->nome }}</label>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @if ($podeSalvarVinculos ?? false)
                <div class="card-footer d-flex flex-wrap gap-2 justify-content-end">
                    <button type="button" class="btn btn-light btn-sm" id="btn-marcar-visiveis">Marcar visíveis</button>
                    <button type="button" class="btn btn-light btn-sm" id="btn-desmarcar-visiveis">Desmarcar visíveis</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-save-line me-1"></i> Salvar vínculos
                    </button>
                </div>
            @endif
        </div>
    </form>
@endsection

@push('scripts')
@if ($podeSalvarVinculos ?? false)
<script>
(function () {
    const filtro = document.getElementById('filtro-frutas');
    const itens = document.querySelectorAll('.fruta-item');

    filtro?.addEventListener('input', () => {
        const termo = filtro.value.trim().toLowerCase();
        itens.forEach((el) => {
            const nome = el.dataset.nome || '';
            el.classList.toggle('d-none', termo !== '' && !nome.includes(termo));
        });
    });

    function forEachVisivel(cb) {
        itens.forEach((el) => {
            if (!el.classList.contains('d-none')) {
                cb(el.querySelector('input[type=checkbox]'));
            }
        });
    }

    document.getElementById('btn-marcar-visiveis')?.addEventListener('click', () => {
        forEachVisivel((input) => { if (input) input.checked = true; });
    });
    document.getElementById('btn-desmarcar-visiveis')?.addEventListener('click', () => {
        forEachVisivel((input) => { if (input) input.checked = false; });
    });
})();
</script>
@endif
@endpush
