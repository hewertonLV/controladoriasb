@php
    /** @var \App\Models\Captacao\CaptacaoLote $lote */
    use App\Support\Captacao\CaptacaoLoteTimelineUi;

    $passosTimeline = CaptacaoLoteTimelineUi::passos($lote);
    $descricaoAtual = CaptacaoLoteTimelineUi::descricaoAtual($lote);
@endphp

<div class="card mb-3 captacao-lote-timeline">
    @if (($modoCabecalho ?? 'timeline') === 'matriz')
        <div class="card-header py-2">
            <strong>Lote #{{ $lote->id }}</strong>
        </div>
    @endif
    <div class="card-body py-2 px-3">
        @include('admin.captacao._lote-info-acoes', [
            'lote' => $lote,
            'modoCabecalho' => $modoCabecalho ?? 'timeline',
            'proximaAcao' => $proximaAcao ?? null,
            'exibirLinkVerLote' => ($modoCabecalho ?? 'timeline') === 'matriz',
            'exibirRomaneioSyncBadge' => $exibirRomaneioSyncBadge ?? false,
        ])
        <div class="captacao-timeline-scroll">
            <ol class="captacao-timeline-steps list-unstyled mb-0">
                @foreach ($passosTimeline as $passo)
                    <li @class([
                        'captacao-timeline-step',
                        'captacao-timeline-step--concluido' => $passo['estado'] === 'concluido',
                        'captacao-timeline-step--atual' => $passo['estado'] === 'atual',
                        'captacao-timeline-step--pendente' => $passo['estado'] === 'pendente',
                    ])>
                        @if ($passo['estado'] !== 'concluido')
                            <span class="captacao-timeline-marker" aria-hidden="true">
                                @if ($passo['estado'] === 'atual')
                                    <i class="ri-focus-3-line"></i>
                                @else
                                    <i class="ri-circle-line"></i>
                                @endif
                            </span>
                        @endif
                        <div class="captacao-timeline-content">
                            <span class="captacao-timeline-label">{{ $passo['label'] }}</span>
                            @if ($passo['estado'] === 'atual')
                                <span class="badge bg-primary-subtle text-primary captacao-timeline-badge">Agora</span>
                            @elseif ($passo['estado'] === 'concluido')
                                <span class="badge bg-success-subtle text-success captacao-timeline-badge">Concluído</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary captacao-timeline-badge">Próximo</span>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        <div class="captacao-timeline-descricao mt-1 mb-0 py-1 px-2 small">
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
            .captacao-lote-timeline .card-header {
                padding: 0.35rem 0.75rem;
            }

            .captacao-timeline-scroll {
                overflow-x: auto;
                overflow-y: hidden;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 0.1rem;
            }

            .captacao-timeline-steps {
                display: flex;
                flex-wrap: nowrap;
                gap: 0.3rem;
                padding: 0;
                min-width: min-content;
            }

            .captacao-timeline-step {
                display: flex;
                align-items: flex-start;
                gap: 0.25rem;
                flex: 0 0 auto;
                min-width: 6.75rem;
                max-width: 8.5rem;
                padding: 0.2rem 0.35rem;
                border-radius: 0.25rem;
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
                width: 1rem;
                height: 1rem;
                flex-shrink: 0;
                font-size: 0.85rem;
                line-height: 1;
                margin-top: 0.05rem;
            }

            .captacao-timeline-step--atual .captacao-timeline-marker {
                color: var(--bs-primary);
            }

            .captacao-timeline-step--pendente .captacao-timeline-marker {
                color: var(--bs-secondary-color);
            }

            .captacao-timeline-content {
                min-width: 0;
                display: flex;
                flex-direction: column;
                gap: 0.1rem;
            }

            .captacao-timeline-label {
                display: block;
                font-size: 0.68rem;
                font-weight: 600;
                line-height: 1.15;
                color: var(--bs-body-color);
            }

            .captacao-timeline-badge {
                align-self: flex-start;
                font-size: 0.6rem;
                font-weight: 500;
                padding: 0.1em 0.4em;
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
