@php
    /** @var \App\Models\Captacao\CaptacaoLote $lote */
    /** @var \App\Enums\CaptacaoLoteAcaoPipeline|null $proximaAcao */
    $proximaAcao = $proximaAcao ?? \App\Support\Captacao\CaptacaoLotePipelineUi::proximaAcao($lote);
@endphp

@if ($proximaAcao !== null)
    @can($proximaAcao->permission())
        @if ($proximaAcao === \App\Enums\CaptacaoLoteAcaoPipeline::FinalizarCaptacaoFaturamento)
            <form method="post" action="{{ route('admin.captacao.faturamento.finalizar') }}" class="d-inline">
                @csrf
                <input type="hidden" name="data_referencia" value="{{ $lote->data_referencia->toDateString() }}">
                <input type="hidden" name="id_unidade_negocio_faturamento" value="{{ $lote->id_unidade_negocio_faturamento }}">
                <input type="hidden" name="id_captacao_lote" value="{{ $lote->id }}">
                <button type="submit" class="btn btn-{{ $proximaAcao->variant() }} btn-sm">
                    {{ $proximaAcao->label() }}
                </button>
            </form>
        @elseif ($proximaAcao === \App\Enums\CaptacaoLoteAcaoPipeline::ConfirmarRomaneioManual)
            <form method="post" action="{{ route('admin.captacao.romaneio-manual.confirmar', $lote) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-{{ $proximaAcao->variant() }} btn-sm">
                    {{ $proximaAcao->label() }}
                </button>
            </form>
        @elseif ($proximaAcao === \App\Enums\CaptacaoLoteAcaoPipeline::IniciarTransferencia)
            @php
                $rotaIniciar = $lote->tipo === \App\Enums\CaptacaoLoteTipo::RomaneioManual
                    ? route('admin.captacao.romaneio-manual.iniciar-transferencia', $lote)
                    : route('admin.captacao.lotes.pipeline.iniciar-transferencia', $lote);
            @endphp
            <form method="post" action="{{ $rotaIniciar }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-{{ $proximaAcao->variant() }} btn-sm">
                    {{ $proximaAcao->label() }}
                </button>
            </form>
        @elseif ($proximaAcao === \App\Enums\CaptacaoLoteAcaoPipeline::ConcluirTransferenciaManual)
            <form method="post" action="{{ route('admin.captacao.romaneio-manual.concluir-transferencia', $lote) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-{{ $proximaAcao->variant() }} btn-sm">
                    {{ $proximaAcao->label() }}
                </button>
            </form>
        @elseif ($proximaAcao === \App\Enums\CaptacaoLoteAcaoPipeline::ValidarTransferencias)
            <form method="post" action="{{ route('admin.captacao.lotes.pipeline.validar-transferencias', $lote) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-{{ $proximaAcao->variant() }} btn-sm">
                    {{ $proximaAcao->label() }}
                </button>
            </form>
        @elseif ($proximaAcao === \App\Enums\CaptacaoLoteAcaoPipeline::ConcluirFrete)
            <form method="post" action="{{ route('admin.captacao.lotes.pipeline.concluir-frete', $lote) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-{{ $proximaAcao->variant() }} btn-sm">
                    {{ $proximaAcao->label() }}
                </button>
            </form>
        @elseif ($proximaAcao === \App\Enums\CaptacaoLoteAcaoPipeline::IniciarFaturamento)
            <form method="post" action="{{ route('admin.captacao.lotes.pipeline.iniciar-faturamento', $lote) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-{{ $proximaAcao->variant() }} btn-sm">
                    {{ $proximaAcao->label() }}
                </button>
            </form>
        @elseif ($proximaAcao === \App\Enums\CaptacaoLoteAcaoPipeline::FinalizarVendas)
            <form method="post" action="{{ route('admin.captacao.lotes.pipeline.finalizar-vendas', $lote) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-{{ $proximaAcao->variant() }} btn-sm">
                    {{ $proximaAcao->label() }}
                </button>
            </form>
        @endif
    @endcan
@endif
