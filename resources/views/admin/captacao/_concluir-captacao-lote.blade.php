@php
    $podeConcluir = $podeConcluirCaptacaoLote ?? false;
    $pendencias = $pendenciasConclusaoCaptacaoLote ?? [];
    $loteAberto = $lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoEmAndamento;
    $loteConcluido = $lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoConcluida;
    $panelId = $panelId ?? 'captacao-concluir-lote-panel';
    $modalId = $panelId.'-modal-pendencias';
@endphp

<div id="{{ $panelId }}" data-lote-status="{{ $lote->status->value }}">
    @if ($loteConcluido)
        <span data-role="badge-concluido" class="badge bg-success-subtle text-success">
            <i class="ri-check-double-line"></i> Captação concluída
        </span>
    @elseif ($loteAberto)
        <div data-role="em-andamento" class="d-flex flex-nowrap align-items-center gap-2">
            @if ($incluirFiltroLojasCaptacao ?? false)
                <input type="search"
                       id="filtro-lojas-captacao"
                       class="form-control form-control-sm"
                       style="width: 220px; min-width: 160px;"
                       placeholder="Buscar loja…"
                       autocomplete="off">
            @endif
            <button type="button"
                    data-role="btn-pendencias"
                    class="btn btn-danger btn-sm captacao-btn-pendencias-conclusao @if (empty($pendencias)) d-none @endif"
                    data-bs-toggle="modal"
                    data-bs-target="#{{ $modalId }}"
                    title="Ver pendências para concluir a captação"
                    aria-label="Ver pendências para concluir a captação">
                <i class="ri-error-warning-fill" aria-hidden="true"></i>
            </button>
            <form method="post"
                  data-role="form-concluir"
                  action="{{ $concluirCaptacaoUrl ?? route('admin.captacao.pedidos-por-loja.concluir-captacao', $lote) }}"
                  class="d-inline flex-shrink-0"
                  @if (! $podeConcluir) onsubmit="return false" @endif>
                @csrf
                <button type="submit"
                        data-role="btn-submit"
                        class="btn btn-success btn-sm text-nowrap"
                        @disabled(! $podeConcluir)
                        title="{{ $podeConcluir ? 'Encerrar captação deste lote' : 'Resolva as pendências listadas' }}">
                    <i class="ri-check-double-line"></i> Concluir captação
                </button>
            </form>
        </div>

        <div class="modal fade"
             id="{{ $modalId }}"
             data-role="modal-pendencias"
             tabindex="-1"
             aria-labelledby="{{ $modalId }}-label"
             aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h5 class="modal-title fs-6" id="{{ $modalId }}-label">
                            Pendências para concluir captação
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body py-3">
                        <p class="text-muted small mb-2">
                            Resolva os itens abaixo para habilitar a conclusão da captação do lote.
                        </p>
                        <ul data-role="lista-pendencias" class="mb-0 ps-3 small">
                            @foreach ($pendencias as $pendencia)
                                <li>{{ $pendencia }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

@once
    @push('styles')
        <style>
            .captacao-btn-pendencias-conclusao {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 2rem;
                height: 2rem;
                padding: 0;
                line-height: 1;
            }

            .captacao-btn-pendencias-conclusao i {
                font-size: 1.15rem;
            }
        </style>
    @endpush
@endonce
