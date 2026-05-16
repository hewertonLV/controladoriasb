@extends('layouts.app')

@section('title', 'Descarte #' . $movimentacao->id)
@section('page-title', 'Movimentação — Descarte')

@section('content')
    <x-admin.flash-messages />

    <div class="card mb-3">
        <div class="card-header d-flex align-items-center flex-wrap gap-2">
            <h4 class="header-title mb-0">Descarte #{{ $movimentacao->id }}</h4>
            <span class="badge bg-secondary">v{{ $movimentacao->versao }}</span>
            <div class="ms-auto d-flex gap-2 flex-wrap">
                @can('movimentacoes.descartes.editar')
                    @if ($movimentacao->status_registro === \App\Enums\MovimentacaoStatusRegistro::ATIVO->value)
                        <a href="{{ route('admin.movimentacoes.descartes.edit', $movimentacao) }}" class="btn btn-primary btn-sm">Editar</a>
                    @endif
                @endcan
                @can('movimentacoes.descartes.visualizar')
                    <a href="{{ route('admin.movimentacoes.descartes.index') }}" class="btn btn-light btn-sm">Lista</a>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Data da movimentação</div>
                    <div class="fw-semibold">{{ $movimentacao->data_movimentacao?->format('d/m/Y H:i') ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Status do registro</div>
                    <div class="fw-semibold">{{ $movimentacao->status_registro }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Unidade (origem)</div>
                    <div class="fw-semibold">{{ $movimentacao->empresaOrigem?->nomeExibicao() ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Fruta</div>
                    <div class="fw-semibold">{{ $movimentacao->fruta?->nome ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Categoria de descarte</div>
                    <div class="fw-semibold">{{ $movimentacao->categoriaDescarte?->nome ?? '—' }}</div>
                </div>
                <div class="col-12">
                    <div class="text-muted small">Motivo do descarte</div>
                    <div class="fw-semibold">{{ $movimentacao->motivo_descarte ?? '—' }}</div>
                </div>
                @if ($movimentacao->observacao)
                    <div class="col-12">
                        <div class="text-muted small">Observação</div>
                        <div class="fw-semibold">{{ $movimentacao->observacao }}</div>
                    </div>
                @endif
            </div>

            <hr class="my-4">

            <h5 class="mb-3">Quantidades e valor econômico</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-muted small">Quantidade (UM)</div>
                    <div class="fw-semibold">{{ number_format((float) $movimentacao->qtd_fruta_um, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Quantidade (kg)</div>
                    <div class="fw-semibold">{{ number_format((float) $movimentacao->qtd_fruta_kg, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Valor econômico da perda</div>
                    <div class="fw-semibold">R$ {{ number_format($movimentacao->valorEconomicoParaRelatorio(), 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Preço médio / kg preservado</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->preco_medio_fruta_kg, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Preço médio / UM preservado</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->preco_medio_fruta_um, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Saldo após (kg)</div>
                    <div class="fw-semibold">{{ number_format((float) $movimentacao->saldo_estoque_fruta_kg, 2, ',', '.') }}</div>
                </div>
            </div>

            @if ($movimentacao->cancelada_em)
                <hr class="my-4">
                <div class="alert alert-warning mb-0">
                    <strong>Cancelado em</strong> {{ $movimentacao->cancelada_em->format('d/m/Y H:i') }}
                    @if ($movimentacao->canceladaPor)
                        por {{ $movimentacao->canceladaPor->name }}
                    @endif
                    @if ($movimentacao->motivo_cancelamento)
                        <div class="mt-1"><strong>Motivo:</strong> {{ $movimentacao->motivo_cancelamento }}</div>
                    @endif
                </div>
            @endif

            @if ($movimentacao->status_registro === \App\Enums\MovimentacaoStatusRegistro::ATIVO->value)
                @can('movimentacoes.descartes.cancelar-admin')
                    <hr class="my-4">
                    <h5 class="mb-3">Cancelamento administrativo</h5>
                    <form method="post" action="{{ route('admin.movimentacoes.descartes.cancelar-admin', $movimentacao) }}" class="row g-2">
                        @csrf
                        <div class="col-12">
                            <label for="motivo_cancel" class="form-label">Motivo</label>
                            <textarea name="motivo" id="motivo_cancel" rows="2" class="form-control" required minlength="3"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Cancelar este descarte administrativamente?');">
                                Cancelar administrativamente
                            </button>
                        </div>
                    </form>
                @endcan
            @endif
        </div>
    </div>
@endsection
