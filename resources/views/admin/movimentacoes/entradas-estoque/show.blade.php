@extends('layouts.app')

@section('title', 'Entrada de estoque #' . $movimentacao->id)
@section('page-title', 'Movimentação — Entrada de estoque')

@section('content')
    <x-admin.flash-messages />

    <div class="card mb-3">
        <div class="card-header d-flex align-items-center flex-wrap gap-2">
            <h4 class="header-title mb-0">Entrada #{{ $movimentacao->id }}</h4>
            <span class="badge bg-success-subtle text-success">Produção</span>
            <div class="ms-auto d-flex gap-2 flex-wrap">
                @can('movimentacoes.entradas-estoque.visualizar')
                    <a href="{{ route('admin.movimentacoes.entradas-estoque.index') }}" class="btn btn-light btn-sm">Lista</a>
                @endcan
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Data</div>
                    <div class="fw-semibold">{{ $movimentacao->data_movimentacao?->format('d/m/Y H:i') ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Status</div>
                    <div class="fw-semibold">{{ $movimentacao->status_registro }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Unidade</div>
                    <div class="fw-semibold">{{ $movimentacao->empresaOrigem?->nomeExibicao() ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Fruta</div>
                    <div class="fw-semibold">{{ $movimentacao->fruta?->nome ?? '—' }} ({{ $movimentacao->fruta?->unidade_medicao ?? '—' }})</div>
                </div>
                @if ($movimentacao->observacao)
                    <div class="col-12">
                        <div class="text-muted small">Observação</div>
                        <div class="fw-semibold">{{ $movimentacao->observacao }}</div>
                    </div>
                @endif
            </div>

            <hr class="my-4">

            <h5 class="mb-3">Quantidades e custos</h5>
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">Quantidade (UM)</div>
                    <div class="fw-semibold">{{ number_format((float) $movimentacao->qtd_fruta_um, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Quantidade (kg)</div>
                    <div class="fw-semibold">{{ number_format((float) $movimentacao->qtd_fruta_kg, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Preço / UM (lançamento)</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->valor_nf_um, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Valor total</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->valor_nf_total, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Custo / kg (deste lote)</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->preco_medio_fruta_kg, 2, ',', '.') }}</div>
                </div>
                @if ($estoque)
                    <div class="col-md-3">
                        <div class="text-muted small">Preço médio / kg (estoque)</div>
                        <div class="fw-semibold">R$ {{ number_format((float) $estoque->preco_medio_kg, 2, ',', '.') }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-muted small">Preço médio / UM (estoque)</div>
                        <div class="fw-semibold">R$ {{ number_format((float) $estoque->preco_medio_um, 2, ',', '.') }}</div>
                    </div>
                @endif
                <div class="col-md-3">
                    <div class="text-muted small">Saldo após (UM)</div>
                    <div class="fw-semibold">{{ number_format((float) $movimentacao->saldo_estoque_fruta_um, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Saldo após (kg)</div>
                    <div class="fw-semibold">{{ number_format((float) $movimentacao->saldo_estoque_fruta_kg, 2, ',', '.') }}</div>
                </div>
            </div>

            @if ($movimentacao->cancelada_em)
                <hr class="my-4">
                <div class="alert alert-warning mb-0">
                    <strong>Cancelada em</strong> {{ $movimentacao->cancelada_em->format('d/m/Y H:i') }}
                    @if ($movimentacao->canceladaPor)
                        por {{ $movimentacao->canceladaPor->name }}
                    @endif
                    @if ($movimentacao->motivo_cancelamento)
                        <div class="mt-1"><strong>Motivo:</strong> {{ $movimentacao->motivo_cancelamento }}</div>
                    @endif
                </div>
            @endif

            @if ($movimentacao->status_registro === \App\Enums\MovimentacaoStatusRegistro::ATIVO->value)
                @can('movimentacoes.entradas-estoque.cancelar-admin')
                    <hr class="my-4">
                    <h5 class="mb-3">Cancelamento administrativo</h5>
                    <form method="post"
                          action="{{ route('admin.movimentacoes.entradas-estoque.cancelar-admin', $movimentacao) }}"
                          class="row g-2"
                          data-confirm="Cancelar esta entrada administrativamente? O estoque será reprocessado."
                          data-confirm-title="Cancelar entrada"
                          data-confirm-variant="danger"
                          data-confirm-btn="Cancelar">
                        @csrf
                        <div class="col-12">
                            <label for="motivo_cancel" class="form-label">Motivo</label>
                            <textarea name="motivo" id="motivo_cancel" rows="2" class="form-control" required minlength="3"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-danger btn-sm">Cancelar entrada</button>
                        </div>
                    </form>
                @endcan
            @endif
        </div>
    </div>
@endsection
