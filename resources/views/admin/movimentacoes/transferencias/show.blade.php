@php
    use App\Enums\MovimentacaoStatusRegistro;
    use App\Enums\StatusTransferenciaOperacional;

    $anchor = $saida->transferencia_origem_id;
    $conforme = ($entrada->status_transferencia ?? '') === StatusTransferenciaOperacional::RECEBIDA_CONFORME->value;
    $cancelada = ($entrada->status_transferencia ?? '') === StatusTransferenciaOperacional::CANCELADA->value
        || $saida->status_registro === MovimentacaoStatusRegistro::CANCELADO->value;
    $parAtivo = $saida->status_registro === MovimentacaoStatusRegistro::ATIVO->value
        && $entrada->status_registro === MovimentacaoStatusRegistro::ATIVO->value;
    $podeVincularFrete = $conforme && $parAtivo;
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
                    <h5 class="mb-0">Entrada (destino)</h5>
                </div>
                <div class="card-body small">
                    <p class="mb-1"><strong>Status transferência:</strong> {{ $entrada->status_transferencia ?? '—' }}</p>
                    <p class="mb-1"><strong>Preço médio calculado (kg):</strong> R$ {{ number_format((float) $entrada->preco_medio_fruta_kg, 2, ',', '.') }}</p>
                    <p class="mb-1"><strong>Custo operacional (histórico):</strong>
                        {{ $entrada->custoOperacionalHistorico?->id ?? '—' }}
                        — R$ {{ number_format((float) $entrada->valor_custo_operacional, 2, ',', '.') }} / kg
                    </p>
                    <p class="mb-1"><strong>ICMS convertido (kg):</strong> R$ {{ number_format((float) $entrada->icms_convertido_kg, 2, ',', '.') }}</p>
                    <p class="mb-1"><strong>Qtd recebida (UM / kg):</strong>
                        {{ number_format((float) ($entrada->qtd_recebida_um ?? $entrada->qtd_fruta_um), 2, ',', '.') }} /
                        {{ number_format((float) ($entrada->qtd_recebida_kg ?? $entrada->qtd_fruta_kg), 2, ',', '.') }}
                    </p>
                    <p class="mb-0"><strong>Versão:</strong> {{ $entrada->versao }} · <strong>Registro:</strong> {{ $entrada->status_registro }}</p>
                </div>
            </div>
        </div>
    </div>

    @if ($podeVincularFrete)
        @can('movimentacoes.transferencias.editar')
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Frete da transferência</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Vincule ou altere o frete. O sistema recalcula o rateio e reprocessa os lançamentos posteriores de estoque no destino.
                    </p>
                    <p class="mb-3 small">
                        <strong>Frete atual:</strong>
                        {{ $saida->frete?->nome ?? 'Sem frete' }}
                        @if ($saida->frete)
                            — R$ {{ number_format((float) $saida->frete->valor, 2, ',', '.') }}
                            · rateio R$ {{ number_format((float) $saida->valor_frete_rateio, 2, ',', '.') }}
                        @endif
                    </p>
                    <form method="POST"
                          action="{{ route('admin.movimentacoes.transferencias.vincular-frete', ['transferenciaOrigem' => $anchor]) }}"
                          class="row g-3 align-items-end">
                        @csrf
                        <div class="col-md-8">
                            <label for="id_frete_vincular" class="form-label">Frete (ABERTO)</label>
                            <select name="id_frete" id="id_frete_vincular" class="form-select @error('id_frete') is-invalid @enderror" data-search-select data-placeholder="Selecione ou pesquise o frete">
                                <option value="">Sem frete</option>
                                @foreach ($fretes ?? [] as $frete)
                                    <option value="{{ $frete->id }}" @selected((string) old('id_frete', $saida->id_frete) === (string) $frete->id)>
                                        {{ $frete->nome }} — R$ {{ number_format((float) $frete->valor, 2, ',', '.') }}
                                    </option>
                                @endforeach
                            </select>
                            @error('id_frete')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4 d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="ri-truck-line me-1"></i> Atualizar frete
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    @endif

    @if ($conforme && $parAtivo)
        @can('movimentacoes.transferencias.cancelar')
            <div class="card mt-3 border-danger border-opacity-25">
                <div class="card-header bg-danger-subtle">
                    <h5 class="mb-0">Cancelar transferência</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        O cancelamento invalida saída e entrada e estorna os estoques de origem e destino.
                    </p>
                    <form method="POST"
                          action="{{ route('admin.movimentacoes.transferencias.cancelar', ['transferenciaOrigem' => $anchor]) }}"
                          class="row g-2 align-items-end"
                          data-confirm="Cancelar esta transferência? Origem e destino serão estornados."
                          data-confirm-title="Cancelar transferência"
                          data-confirm-variant="danger"
                          data-confirm-btn="Cancelar">
                        @csrf
                        <div class="col-md-9">
                            <label for="motivo-cancelar-transferencia" class="form-label">Motivo</label>
                            <input id="motivo-cancelar-transferencia"
                                   name="motivo_substituicao"
                                   class="form-control"
                                   required
                                   placeholder="Motivo do cancelamento">
                        </div>
                        <div class="col-md-3 d-grid">
                            <button class="btn btn-danger" type="submit">Cancelar transferência</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan

        @can('movimentacoes.transferencias.cancelar-admin')
            <div class="card mt-3 border-danger border-opacity-25">
                <div class="card-header bg-danger-subtle">
                    <h5 class="mb-0">Cancelar transferência (administrativo)</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Cancelamento administrativo com auditoria completa e replay de estoque em origem e destino.
                    </p>
                    <form method="POST"
                          action="{{ route('admin.movimentacoes.transferencias.cancelar-admin', ['transferenciaOrigem' => $anchor]) }}"
                          class="row g-2 align-items-end"
                          data-confirm="Cancelar esta transferência? Origem e destino serão recalculados."
                          data-confirm-title="Cancelar transferência"
                          data-confirm-variant="danger"
                          data-confirm-btn="Cancelar">
                        @csrf
                        <div class="col-md-9">
                            <label for="motivo-cancelar-transferencia-admin" class="form-label">Motivo</label>
                            <input id="motivo-cancelar-transferencia-admin"
                                   name="motivo"
                                   class="form-control"
                                   required
                                   placeholder="Motivo do cancelamento administrativo">
                        </div>
                        <div class="col-md-3 d-grid">
                            <button class="btn btn-danger" type="submit">Cancelar (admin)</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    @endif

    @if ($cancelada)
        <div class="card mt-3 border-secondary">
            <div class="card-body">
                <h5 class="fs-14 text-uppercase text-muted mb-2">Transferência cancelada</h5>
                <p class="mb-1">
                    <strong>Cancelada em:</strong>
                    {{ $saida->cancelada_em?->format('d/m/Y H:i') ?? $saida->substituida_em?->format('d/m/Y H:i') ?? '—' }}
                </p>
                <p class="mb-1">
                    <strong>Responsável:</strong> {{ $saida->canceladaPor?->name ?? '—' }}
                </p>
                <p class="mb-0">
                    <strong>Motivo:</strong> {{ $saida->motivo_cancelamento ?: $saida->motivo_substituicao ?: '—' }}
                </p>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    @include('partials.admin.movimentacoes-form-scripts')
@endpush
