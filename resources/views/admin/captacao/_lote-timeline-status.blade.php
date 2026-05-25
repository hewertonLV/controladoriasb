@php
    /** @var \App\Models\Captacao\CaptacaoLote $lote */
    use App\Support\Captacao\CaptacaoLoteTimelineUi;

    $passosTimeline = CaptacaoLoteTimelineUi::passos($lote);
    $descricaoAtual = CaptacaoLoteTimelineUi::descricaoAtual($lote);
@endphp

<div class="card mb-3 captacao-lote-timeline">
    <div class="card-header py-2 d-flex flex-wrap gap-2 align-items-center justify-content-between">
        @if (($modoCabecalho ?? 'timeline') === 'matriz')
            <div class="d-flex flex-wrap gap-3 align-items-center">
                <p class="mb-0"><strong>Lote #{{ $lote->id }}</strong> — {{ $lote->data_referencia->format('d/m/Y') }} — {{ $lote->status->label() }}</p>
                @if ($lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoEmAndamento)
                    <span class="badge bg-secondary" id="matriz-sync-badge">sincronizado</span>
                @endif
                <a href="{{ route('admin.captacao.lotes.show', $lote) }}" class="btn btn-sm btn-light" title="Romaneios e detalhes do lote">
                    <i class="ri-eye-line me-1"></i> Ver lote
                </a>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                @if ($lote->status === \App\Enums\CaptacaoLoteStatus::AguardandoVinculoFrete)
                    @can('captacao.lote.frete.vincular')
                        <a href="{{ route('admin.captacao.lotes.fretes.index', $lote) }}" class="btn btn-sm btn-soft-warning">Vincular frete (opcional)</a>
                    @endcan
                @endif
                @include('admin.captacao._lote-pipeline-acoes', ['lote' => $lote])
            </div>
        @else
            <strong>Linha do tempo do lote</strong>
        @endif
    </div>
    <div class="card-body pb-2">
        <div class="captacao-timeline-scroll">
            <ol class="captacao-timeline-steps list-unstyled mb-0">
                @foreach ($passosTimeline as $passo)
                    <li @class([
                        'captacao-timeline-step',
                        'captacao-timeline-step--concluido' => $passo['estado'] === 'concluido',
                        'captacao-timeline-step--atual' => $passo['estado'] === 'atual',
                        'captacao-timeline-step--pendente' => $passo['estado'] === 'pendente',
                    ])>
                        <span class="captacao-timeline-marker" aria-hidden="true">
                            @if ($passo['estado'] === 'concluido')
                                <i class="ri-check-line"></i>
                            @elseif ($passo['estado'] === 'atual')
                                <i class="ri-focus-3-line"></i>
                            @else
                                <i class="ri-circle-line"></i>
                            @endif
                        </span>
                        <div class="captacao-timeline-content">
                            <span class="captacao-timeline-label">{{ $passo['label'] }}</span>
                            @if ($passo['estado'] === 'atual')
                                <span class="badge bg-primary-subtle text-primary">Agora</span>
                            @elseif ($passo['estado'] === 'concluido')
                                <span class="badge bg-success-subtle text-success">Concluído</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">Próximo</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="captacao-timeline-descricao mt-2 mb-0 py-2 px-3 small">
            <i class="ri-information-line me-1"></i>
            <strong>Neste momento:</strong> {{ $descricaoAtual }}
        </div>
    </div>
    @if (($modoCabecalho ?? 'timeline') === 'matriz' && $lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoEmAndamento)
        <div class="card-footer py-2 text-muted small">
            Selecione a loja na última linha · ↑↓ na célula · atualização a cada 2s
        </div>
    @endif
</div>

@once
    @push('head')
        <style>
            .captacao-timeline-scroll {
                overflow-x: auto;
                overflow-y: hidden;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 0.25rem;
            }

            .captacao-timeline-steps {
                display: flex;
                flex-wrap: nowrap;
                gap: 0.5rem;
                padding: 0;
                min-width: min-content;
            }

            .captacao-timeline-step {
                display: flex;
                align-items: flex-start;
                gap: 0.4rem;
                flex: 0 0 auto;
                min-width: 9.5rem;
                max-width: 11rem;
                padding: 0.4rem 0.55rem;
                border-radius: 0.375rem;
                --captacao-timeline-step-bg: transparent;
                --captacao-timeline-step-border: var(--bs-border-color);
                background-color: var(--captacao-timeline-step-bg);
                box-shadow: inset 3px 0 0 var(--captacao-timeline-step-border);
            }

            .captacao-timeline-step--concluido {
                --captacao-timeline-step-bg: rgba(var(--bs-success-rgb), 0.1);
                --captacao-timeline-step-border: var(--bs-success);
            }

            .captacao-timeline-step--atual {
                --captacao-timeline-step-bg: rgba(var(--bs-primary-rgb), 0.12);
                --captacao-timeline-step-border: var(--bs-primary);
            }

            .captacao-timeline-step--pendente {
                --captacao-timeline-step-bg: rgba(var(--bs-secondary-rgb), 0.08);
                --captacao-timeline-step-border: var(--bs-border-color);
            }

            [data-bs-theme='dark'] .captacao-timeline-step--concluido {
                --captacao-timeline-step-bg: rgba(var(--bs-success-rgb), 0.16);
            }

            [data-bs-theme='dark'] .captacao-timeline-step--atual {
                --captacao-timeline-step-bg: rgba(var(--bs-primary-rgb), 0.2);
            }

            [data-bs-theme='dark'] .captacao-timeline-step--pendente {
                --captacao-timeline-step-bg: rgba(var(--bs-secondary-rgb), 0.14);
            }

            .captacao-timeline-marker {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 1.35rem;
                height: 1.35rem;
                flex-shrink: 0;
                font-size: 0.95rem;
            }

            .captacao-timeline-step--concluido .captacao-timeline-marker {
                color: var(--bs-success);
            }

            .captacao-timeline-step--atual .captacao-timeline-marker {
                color: var(--bs-primary);
            }

            .captacao-timeline-step--pendente .captacao-timeline-marker {
                color: var(--bs-secondary-color);
            }

            .captacao-timeline-content {
                min-width: 0;
            }

            .captacao-timeline-label {
                display: block;
                font-size: 0.75rem;
                font-weight: 600;
                line-height: 1.2;
                color: var(--bs-body-color);
            }

            .captacao-timeline-step .badge {
                font-size: 0.65rem;
                margin-top: 0.15rem;
            }

            .captacao-timeline-descricao {
                color: var(--bs-body-color);
                background: rgba(var(--bs-primary-rgb), 0.08);
                border: 1px solid rgba(var(--bs-primary-rgb), 0.2);
                border-radius: 0.375rem;
            }

            [data-bs-theme='dark'] .captacao-timeline-descricao {
                background: rgba(var(--bs-primary-rgb), 0.14);
                border-color: rgba(var(--bs-primary-rgb), 0.35);
            }
        </style>
    @endpush
@endonce
