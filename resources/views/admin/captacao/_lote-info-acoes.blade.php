@php
    /** @var \App\Models\Captacao\CaptacaoLote $lote */
    /** @var \App\Enums\CaptacaoLoteAcaoPipeline|null $proximaAcao */
    $exibirLinkVerLote = $exibirLinkVerLote ?? false;
    $exibirRomaneioSyncBadge = $exibirRomaneioSyncBadge ?? false;
@endphp

<p class="mb-2 small text-muted d-flex flex-nowrap align-items-center gap-3 overflow-x-auto text-nowrap">
    <span><strong class="text-body">Data:</strong> {{ $lote->data_referencia->format('d/m/Y') }}</span>
    <span class="text-secondary" aria-hidden="true">·</span>
    <span><strong class="text-body">Faturamento:</strong> {{ $lote->unidadeFaturamento->nome }}</span>
    <span class="text-secondary" aria-hidden="true">·</span>
    <span><strong class="text-body">Galpão:</strong> {{ $lote->unidadeGalpao->nome }}</span>
    <span class="text-secondary" aria-hidden="true">·</span>
    <span><strong class="text-body">Status atual:</strong> {{ $lote->status->label() }}</span>
</p>

<div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
    @if ($exibirLinkVerLote)
        <a href="{{ route('admin.captacao.lotes.show', $lote) }}" class="btn btn-sm btn-light" title="Romaneios e detalhes do lote">
            <i class="ri-eye-line me-1"></i> Romaneio
        </a>
    @endif

    @if (
        $lote->tipo === \App\Enums\CaptacaoLoteTipo::CaptacaoPedidos
        && ($lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoEmAndamento || $lote->status->permiteEdicaoPreco())
        && ($modoCabecalho ?? 'timeline') !== 'matriz'
    )
        <a href="{{ route('admin.captacao.matriz.index', ['lote' => $lote->id]) }}" class="btn btn-primary btn-sm">
            <i class="ri-pencil-line me-1"></i>
            Captação
        </a>
    @endif

    @if ($lote->tipo === \App\Enums\CaptacaoLoteTipo::CaptacaoPedidos && $lote->status->exibeAbaArquivoCiganTransferencia())
        <a href="{{ route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'arquivo-cigan']) }}" class="btn btn-soft-info btn-sm">
            <i class="ri-download-2-line me-1"></i> Arquivo Cigam
        </a>
    @endif

    @if ($lote->status->exibeAbaArquivoCiganVendas())
        @can('captacao.lote.faturamento.iniciar')
            <a href="{{ route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'arquivo-cigan']) }}" class="btn btn-soft-info btn-sm">
                <i class="ri-download-2-line me-1"></i> Arquivo Cigam Venda
            </a>
        @endcan
    @endif

    @if ($lote->status === \App\Enums\CaptacaoLoteStatus::VendasFinalizadas)
        @can('captacao.lote.frete.vincular')
            <a href="{{ route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'frete-vendas']) }}" class="btn btn-sm btn-soft-warning">Frete Vendas (opcional)</a>
        @endcan
    @endif

    @if ($exibirRomaneioSyncBadge)
        <span class="badge bg-success" id="romaneio-sync-badge">sincronizado</span>
    @endif

    @if (($modoCabecalho ?? 'timeline') === 'matriz' && $lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoEmAndamento)
        <span class="badge bg-secondary" id="matriz-sync-badge">sincronizado</span>
    @endif

    @include('admin.captacao._lote-pipeline-acoes', ['lote' => $lote, 'proximaAcao' => $proximaAcao ?? null])
</div>
