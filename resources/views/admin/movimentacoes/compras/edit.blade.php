@extends('layouts.app')

@section('title', 'Editar compra #' . ($movimentacao->numero_compra ?? $movimentacao->id))
@section('page-title', 'Movimentação — Ajustar valor da NF')

@section('content')
    <x-admin.flash-messages />

    <div class="card mb-3">
        <div class="card-header d-flex align-items-center gap-2">
            <h4 class="header-title mb-0">Contexto da compra</h4>
            <a href="{{ route('admin.movimentacoes.compras.show', $movimentacao) }}" class="btn btn-light btn-sm ms-auto">Cancelar</a>
        </div>
        <div class="card-body">
            <div class="row g-2 small">
                @if ($movimentacao->movimentacao_origem_id)
                    <div class="col-md-4"><span class="text-muted">Compra:</span> #{{ $movimentacao->numero_compra ?? $movimentacao->movimentacao_origem_id }}</div>
                    <div class="col-md-4"><span class="text-muted">Versão ativa:</span> #{{ $movimentacao->id }} (v{{ $movimentacao->versao }})</div>
                    <div class="col-md-4"><span class="text-muted">Versão anterior:</span> {{ $movimentacao->versaoAnterior ? '#'.$movimentacao->versaoAnterior->id : '—' }}</div>
                @endif
                <div class="col-md-4"><span class="text-muted">Data da movimentação:</span> {{ $movimentacao->data_movimentacao?->format('d/m/Y H:i') ?? '—' }}</div>
                <div class="col-md-4"><span class="text-muted">Data da atualização:</span> {{ $movimentacao->versao > 1 ? $movimentacao->created_at?->format('d/m/Y H:i') : '—' }}</div>
                <div class="col-md-4"><span class="text-muted">Fornecedor:</span> {{ $movimentacao->empresaOrigem?->nomeExibicao() }}</div>
                <div class="col-md-4"><span class="text-muted">Unidade:</span> {{ $movimentacao->empresaDestino?->nomeExibicao() }}</div>
                <div class="col-md-4"><span class="text-muted">Fruta:</span> {{ $movimentacao->fruta?->nome }}</div>
                <div class="col-md-4"><span class="text-muted">Quantidade (UM):</span> {{ number_format((float) $movimentacao->qtd_fruta_um, 2, ',', '.') }}</div>
                <div class="col-md-4"><span class="text-muted">Quantidade (kg):</span> {{ number_format((float) $movimentacao->qtd_fruta_kg, 2, ',', '.') }}</div>
                <div class="col-md-4"><span class="text-muted">Frete:</span> {{ $movimentacao->frete?->nome }}</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h4 class="header-title mb-0">Novo valor da nota fiscal</h4>
            <p class="text-muted mb-0 small">Demais campos são recalculados automaticamente pelo sistema (nova versão da movimentação).</p>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.movimentacoes.compras.update', $movimentacao) }}" class="row g-3">
                @csrf
                @method('PUT')

                <div class="col-md-6">
                    <label for="valor_nf_total" class="form-label">Valor total da nota fiscal <span class="text-danger">*</span></label>
                    @php
                        $valorNfTotal = old('valor_nf_total');
                        if ($valorNfTotal === null) {
                            $valorNfTotal = number_format((float) $movimentacao->valor_nf_total, 2, ',', '.');
                        }
                    @endphp
                    <input type="text"
                           name="valor_nf_total"
                           id="valor_nf_total"
                           data-mask-decimal-br-cents
                           value="{{ $valorNfTotal }}"
                           class="form-control @error('valor_nf_total') is-invalid @enderror"
                           inputmode="numeric"
                           autocomplete="off"
                           placeholder=""
                           required>
                    @error('valor_nf_total')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <label for="motivo_substituicao" class="form-label">Motivo da substituição</label>
                    <textarea name="motivo_substituicao" id="motivo_substituicao" rows="3" maxlength="2000"
                              class="form-control @error('motivo_substituicao') is-invalid @enderror">{{ old('motivo_substituicao') }}</textarea>
                    @error('motivo_substituicao')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-check-line me-1"></i> Registrar nova versão
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    @include('partials.admin.movimentacoes-form-scripts')
@endpush
