@extends('layouts.app')

@section('title', 'Lote de captação')
@section('page-title', 'Lote de captação #'.$lote->id)

@section('content')
    <x-admin.flash-messages />

    @include('admin.captacao._lote-timeline-status', ['lote' => $lote])

    <div class="card mb-3">
        <div class="card-body">
            <p class="mb-2 small text-muted d-flex flex-nowrap align-items-center gap-3 overflow-x-auto text-nowrap">
                <span><strong class="text-body">Data:</strong> {{ $lote->data_referencia->format('d/m/Y') }}</span>
                <span class="text-secondary" aria-hidden="true">·</span>
                <span><strong class="text-body">Faturamento:</strong> {{ $lote->unidadeFaturamento->nome }}</span>
                <span class="text-secondary" aria-hidden="true">·</span>
                <span><strong class="text-body">Galpão:</strong> {{ $lote->unidadeGalpao->nome }}</span>
                <span class="text-secondary" aria-hidden="true">·</span>
                <span><strong class="text-body">Status atual:</strong> {{ $lote->status->label() }}</span>
            </p>
            <div class="d-flex flex-wrap gap-2 mt-2 align-items-center">
                @if ($lote->tipo === \App\Enums\CaptacaoLoteTipo::CaptacaoPedidos && ($lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoEmAndamento || $lote->status->permiteEdicaoPreco()))
                    <a href="{{ route('admin.captacao.matriz.index', ['lote' => $lote->id]) }}" class="btn btn-primary btn-sm">
                        <i class="ri-pencil-line me-1"></i> {{ $lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoEmAndamento ? 'Editar captação (matriz)' : 'Editar preços (matriz)' }}
                    </a>
                @endif
                @if ($lote->tipo === \App\Enums\CaptacaoLoteTipo::CaptacaoPedidos && $lote->status->exibeAbaArquivoCiganTransferencia())
                    <a href="{{ route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'arquivo-cigan']) }}" class="btn btn-soft-info btn-sm">
                        <i class="ri-download-2-line me-1"></i> Arquivo Cigan (matriz)
                    </a>
                @endif
                @if ($lote->status === \App\Enums\CaptacaoLoteStatus::AguardandoVinculoFrete)
                    @can('captacao.lote.frete.vincular')
                        <a href="{{ route('admin.captacao.lotes.fretes.index', $lote) }}" class="btn btn-sm btn-soft-warning">Vincular frete (opcional)</a>
                    @endcan
                @endif
                @include('admin.captacao._lote-pipeline-acoes', ['lote' => $lote])
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#romaneio1" type="button">Romaneio 1 — Carregamento</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#romaneio2" type="button">Romaneio 2 — Abastecimento</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="romaneio1">
            <div class="card"><div class="card-body table-responsive">
                @include('admin.captacao._romaneio-carregamento-tabela', [
                    'romaneioCarregamento' => $romaneioCarregamento,
                    'romaneioCarregamentoTotaisGerais' => $romaneioCarregamentoTotaisGerais,
                ])
            </div></div>
        </div>
        <div class="tab-pane fade" id="romaneio2">
            <div class="card"><div class="card-body table-responsive">
                @include('admin.captacao._romaneio-abastecimento-tabela', ['romaneioAbastecimento' => $romaneioAbastecimento])
            </div></div>
        </div>
    </div>
@endsection
