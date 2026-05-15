@extends('layouts.app')

@section('title', 'Compra #' . $movimentacao->id)
@section('page-title', 'Movimentação — Compra')

@section('content')
    <x-admin.flash-messages />

    <div class="card mb-3">
        <div class="card-header d-flex align-items-center flex-wrap gap-2">
            <h4 class="header-title mb-0">Compra #{{ $movimentacao->id }}</h4>
            <span class="badge bg-secondary">v{{ $movimentacao->versao }}</span>
            <div class="ms-auto d-flex gap-2">
                @can('movimentacoes.compras.editar')
                    <a href="{{ route('admin.movimentacoes.compras.edit', $movimentacao) }}" class="btn btn-primary btn-sm">Editar valor da NF</a>
                @endcan
                @can('movimentacoes.compras.visualizar')
                    <a href="{{ route('admin.movimentacoes.compras.index') }}" class="btn btn-light btn-sm">Lista</a>
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
                    <div class="text-muted small">Fornecedor (origem)</div>
                    <div class="fw-semibold">{{ $movimentacao->empresaOrigem?->nomeExibicao() ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Unidade (destino)</div>
                    <div class="fw-semibold">{{ $movimentacao->empresaDestino?->nomeExibicao() ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Fruta</div>
                    <div class="fw-semibold">{{ $movimentacao->fruta?->nome ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Frete</div>
                    <div class="fw-semibold">{{ $movimentacao->frete?->nome ?? '—' }}</div>
                </div>
            </div>

            <hr class="my-4">

            <h5 class="mb-3">Valores informados e calculados</h5>
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
                    <div class="text-muted small">Valor total NF</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->valor_nf_total, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Valor NF / UM</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->valor_nf_um, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Valor NF / kg</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->valor_nf_kg, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">ICMS convertido (kg)</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->icms_convertido_kg, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Rateio frete</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->valor_frete_rateio, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Frete / UM</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->valor_frete_um, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Frete / kg</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->valor_frete_kg, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Custo operacional (histórico)</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->valor_custo_operacional, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Saldo estoque (kg)</div>
                    <div class="fw-semibold">{{ number_format((float) $movimentacao->saldo_estoque_fruta_kg, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Saldo estoque (UM)</div>
                    <div class="fw-semibold">{{ number_format((float) $movimentacao->saldo_estoque_fruta_um, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Preço médio fruta (kg) — lote</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->preco_medio_fruta_kg, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Preço médio fruta (UM) — lote</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->preco_medio_fruta_um, 2, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>
@endsection
