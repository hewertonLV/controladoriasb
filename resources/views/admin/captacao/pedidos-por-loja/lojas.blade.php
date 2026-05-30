@extends('layouts.app')

@section('title', 'Lojas — '.$lote->carteira?->nome)
@section('page-title', 'Captação por loja — '.$lote->carteira?->nome)

@section('content')
    @include('admin.captacao.pedidos-por-loja._card-estilos')

    <div class="page-container">
        @if ($captacaoLoteConcluida ?? false)
            <div class="alert alert-success mb-3">
                <strong>Captação concluída.</strong>
                Este lote foi encerrado e não pode ser reaberto. As lojas abaixo estão disponíveis apenas para consulta.
            </div>
        @endif

        <div class="mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <a href="{{ route('admin.captacao.pedidos-por-loja.carteiras', ['data_referencia' => $lote->data_referencia->format('Y-m-d')]) }}"
                   class="btn btn-sm btn-light"><i class="ri-arrow-left-line"></i> Carteiras</a>
                <a href="{{ route('admin.captacao.matriz.index', ['lote' => $lote->id]) }}"
                   class="btn btn-sm btn-outline-secondary ms-1">Matriz</a>
            </div>
            @unless ($captacaoLoteConcluida ?? false)
                @include('admin.captacao._concluir-captacao-lote', [
                    'lote' => $lote,
                    'podeConcluirCaptacaoLote' => $podeConcluirCaptacaoLote ?? false,
                    'pendenciasConclusaoCaptacaoLote' => $pendenciasConclusaoCaptacaoLote ?? [],
                    'incluirFiltroLojasCaptacao' => $lojas->isNotEmpty(),
                ])
            @else
                <div class="d-flex flex-nowrap align-items-center gap-2 ms-auto">
                    @if ($lojas->isNotEmpty())
                        <input type="search"
                               id="filtro-lojas-captacao"
                               class="form-control form-control-sm"
                               style="width: 220px; min-width: 160px;"
                               placeholder="Buscar loja…"
                               autocomplete="off">
                    @endif
                    <span class="badge bg-success-subtle text-success text-nowrap">
                        <i class="ri-check-double-line"></i> Captação concluída
                    </span>
                </div>
            @endunless
        </div>

        @unless ($captacaoLoteConcluida ?? false)
            @include('admin.captacao._concluir-captacao-lote-sync', ['lote' => $lote])
        @endunless

        @if ($lojas->isEmpty())
            <div class="alert alert-warning">
                Nenhuma loja vinculada a esta carteira.
                <a href="{{ route('admin.captacao.carteiras.edit', $lote->carteira) }}">Edite a carteira</a>
                para incluir lojas.
            </div>
        @endif

        <div class="row g-3" id="lista-lojas-captacao">
            @foreach ($lojas as $entrada)
                @php
                    $cliente = $entrada['cliente'];
                    $estado = $entrada['estado'];
                    $nomeLoja = $cliente->fantasia ?: $cliente->razao_social;
                @endphp
                <div class="col-sm-6 col-md-4 col-lg-3 captacao-loja-card-col"
                     data-nome="{{ mb_strtolower($nomeLoja) }}">
                    <a href="{{ route('admin.captacao.pedidos-por-loja.show', [$lote, $cliente]) }}"
                       class="card captacao-loja-card estado-{{ $estado['estado'] }} shadow-sm @if($captacaoLoteConcluida ?? false) opacity-75 @endif">
                        <div class="card-body p-3">
                            <div class="fw-semibold text-truncate" title="{{ $nomeLoja }}">
                                {{ $nomeLoja }}
                            </div>
                            @if ($estado['estado'] === 'concluido')
                                @if ($estado['rentabilidade']['margem_percentual'] !== null)
                                    <div class="small text-success mt-2">
                                        Rent. {{ $estado['rentabilidade']['margem_percentual'] }}%
                                        · R$ {{ number_format((float) $estado['rentabilidade']['margem_total'], 2, ',', '.') }}
                                    </div>
                                @else
                                    <div class="small text-success mt-2">Concluído</div>
                                @endif
                            @elseif ($estado['estado'] === 'em_andamento')
                                <div class="small text-warning mt-2">Em andamento</div>
                            @else
                                <div class="small text-muted mt-2">Não iniciado</div>
                            @endif
                            @if (! $entrada['possui_frutas'])
                                <div class="small text-danger mt-1">Sem frutas vinculadas</div>
                            @endif
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const filtro = document.getElementById('filtro-lojas-captacao');
    const itens = document.querySelectorAll('.captacao-loja-card-col');
    if (!filtro || itens.length === 0) return;

    filtro.addEventListener('input', () => {
        const termo = filtro.value.trim().toLowerCase();
        itens.forEach((el) => {
            const nome = el.dataset.nome || '';
            el.classList.toggle('d-none', termo !== '' && !nome.includes(termo));
        });
    });
})();
</script>
@endpush
