@php
    use App\Enums\StatusRecebimentoTransferencia;
    use App\Enums\StatusTransferenciaOperacional;

    $anchor = $saida->transferencia_origem_id;
    $pendente = ($entrada->status_transferencia ?? '') === StatusTransferenciaOperacional::PENDENTE_RECEBIMENTO->value;
    $divergente = ($entrada->status_transferencia ?? '') === StatusTransferenciaOperacional::RECEBIDA_DIVERGENTE->value;
@endphp

@extends('layouts.app')

@section('title', 'Transferência')
@section('page-title', 'Movimentação — Transferência')

@section('content')
    <x-admin.flash-messages />

    <div class="d-flex flex-wrap gap-2 mb-3">
        @can('movimentacoes.transferencias.visualizar')
            <a href="{{ route('admin.movimentacoes.transferencias.index') }}" class="btn btn-light btn-sm">Lista</a>
        @endcan
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Saída (origem)</h5>
                </div>
                <div class="card-body small">
                    <p class="mb-1"><strong>Status transferência:</strong> {{ $saida->status_transferencia ?? '—' }}</p>
                    <p class="mb-1"><strong>Fruta:</strong> {{ $saida->fruta?->nome ?? '—' }}</p>
                    <p class="mb-1"><strong>Qtd enviada (UM / kg):</strong>
                        {{ number_format((float) $saida->qtd_fruta_um, 2, ',', '.') }} /
                        {{ number_format((float) $saida->qtd_fruta_kg, 2, ',', '.') }}
                    </p>
                    <p class="mb-1"><strong>Preço médio origem (kg):</strong> R$ {{ number_format((float) $saida->preco_medio_fruta_kg, 2, ',', '.') }}</p>
                    <p class="mb-1"><strong>Frete (kg / rateio):</strong>
                        R$ {{ number_format((float) $saida->valor_frete_kg, 2, ',', '.') }} /
                        R$ {{ number_format((float) $saida->valor_frete_rateio, 2, ',', '.') }}
                    </p>
                    <p class="mb-0"><strong>Versão:</strong> {{ $saida->versao }} · <strong>Registro:</strong> {{ $saida->status_registro }}</p>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">Entrada (destino — pendente até conferência)</h5>
                </div>
                <div class="card-body small">
                    <p class="mb-1"><strong>Status transferência:</strong> {{ $entrada->status_transferencia ?? '—' }}</p>
                    <p class="mb-1"><strong>Preço médio calculado (kg):</strong> R$ {{ number_format((float) $entrada->preco_medio_fruta_kg, 2, ',', '.') }}</p>
                    <p class="mb-1"><strong>Custo operacional (histórico):</strong>
                        {{ $entrada->custoOperacionalHistorico?->id ?? '—' }}
                        — R$ {{ number_format((float) $entrada->valor_custo_operacional, 2, ',', '.') }} / kg
                    </p>
                    <p class="mb-1"><strong>ICMS convertido (kg):</strong> R$ {{ number_format((float) $entrada->icms_convertido_kg, 2, ',', '.') }}</p>
                    <p class="mb-1"><strong>Recebimento:</strong> {{ $entrada->status_recebimento ?? '—' }}</p>
                    <p class="mb-0"><strong>Versão:</strong> {{ $entrada->versao }} · <strong>Registro:</strong> {{ $entrada->status_registro }}</p>
                </div>
            </div>
        </div>
    </div>

    @if ($pendente)
        @can('movimentacoes.transferencias.receber')
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Conferência no destino</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.movimentacoes.transferencias.recebimento.store', ['transferenciaOrigem' => $anchor]) }}" class="row g-3">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status_recebimento" class="form-select @error('status_recebimento') is-invalid @enderror" required>
                                <option value="">Selecione…</option>
                                <option value="{{ StatusRecebimentoTransferencia::CONFORME->value }}" @selected(old('status_recebimento') === StatusRecebimentoTransferencia::CONFORME->value)>Conforme</option>
                                <option value="{{ StatusRecebimentoTransferencia::DIVERGENTE->value }}" @selected(old('status_recebimento') === StatusRecebimentoTransferencia::DIVERGENTE->value)>Divergente</option>
                            </select>
                            @error('status_recebimento')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Qtd recebida (UM) <span class="text-danger">*</span></label>
                            <input type="text" name="qtd_recebida_um" value="{{ old('qtd_recebida_um', $entrada->qtd_fruta_um) }}" class="form-control @error('qtd_recebida_um') is-invalid @enderror" data-mask-decimal-br required>
                            @error('qtd_recebida_um')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">NF destino</label>
                            <input type="text" name="numero_nf_destino" value="{{ old('numero_nf_destino') }}" class="form-control @error('numero_nf_destino') is-invalid @enderror">
                            @error('numero_nf_destino')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observação recebimento</label>
                            <textarea name="observacao_recebimento" rows="2" class="form-control @error('observacao_recebimento') is-invalid @enderror">{{ old('observacao_recebimento') }}</textarea>
                            @error('observacao_recebimento')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <small class="text-muted">Obrigatória quando o status for Divergente.</small>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Registrar recebimento</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    @endif

    @if ($divergente)
        <div class="row g-3 mt-1">
            @can('movimentacoes.transferencias.reenviar')
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header"><h5 class="mb-0">Reenviar (origem)</h5></div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('admin.movimentacoes.transferencias.reenviar', ['transferenciaOrigem' => $anchor]) }}" class="row g-2">
                                @csrf
                                <div class="col-12">
                                    <label class="form-label">Nova quantidade (UM) <span class="text-danger">*</span></label>
                                    <input type="text" name="qtd_fruta_um" value="{{ old('qtd_fruta_um', $saida->qtd_fruta_um) }}" class="form-control" data-mask-decimal-br required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Frete</label>
                                    <select name="id_frete" class="form-select">
                                        <option value="">Manter / sem frete</option>
                                        @foreach (\App\Models\Frete::query()->where('status_situacao', \App\Enums\FreteStatusSituacao::ABERTA->value)->orderBy('nome')->get() as $frete)
                                            <option value="{{ $frete->id }}" @selected((string) old('id_frete') === (string) $frete->id)>{{ $frete->nome }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">NF origem</label>
                                    <input type="text" name="numero_nf_origem" value="{{ old('numero_nf_origem', $saida->numero_nf_origem) }}" class="form-control">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Observação</label>
                                    <textarea name="observacao" rows="2" class="form-control">{{ old('observacao', $saida->observacao) }}</textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Motivo da substituição</label>
                                    <textarea name="motivo_substituicao" rows="2" class="form-control">{{ old('motivo_substituicao') }}</textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-warning">Reenviar transferência</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endcan
            @can('movimentacoes.transferencias.cancelar')
                <div class="col-lg-6">
                    <div class="card border-danger">
                        <div class="card-header bg-danger-subtle"><h5 class="mb-0">Cancelar transferência</h5></div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('admin.movimentacoes.transferencias.cancelar', ['transferenciaOrigem' => $anchor]) }}" class="row g-2" onsubmit="return confirm('Cancelar esta transferência e estornar a origem?');">
                                @csrf
                                <div class="col-12">
                                    <label class="form-label">Motivo</label>
                                    <textarea name="motivo_substituicao" rows="2" class="form-control">{{ old('motivo_substituicao') }}</textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-outline-danger">Cancelar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            @endcan
        </div>
    @endif
@endsection

@push('scripts')
    @include('admin.movimentacoes.compras._masks')
@endpush
