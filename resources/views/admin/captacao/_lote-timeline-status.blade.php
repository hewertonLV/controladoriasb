@php
    /** @var \App\Models\Captacao\CaptacaoLote $lote */
    use App\Support\Captacao\CaptacaoLoteTimelineUi;

    $passosTimeline = CaptacaoLoteTimelineUi::passos($lote);
    $descricaoAtual = CaptacaoLoteTimelineUi::descricaoAtual($lote);
@endphp

<div class="card mb-3 captacao-lote-timeline">
    <div class="card-header py-2">
        <strong>Linha do tempo do lote</strong>
    </div>
    <div class="card-body">
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
                            <span class="badge bg-primary-subtle text-primary ms-1">Agora</span>
                        @elseif ($passo['estado'] === 'concluido')
                            <span class="badge bg-success-subtle text-success ms-1">Concluído</span>
                        @else
                            <span class="badge bg-light text-muted ms-1">Próximo</span>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>

        <div class="alert alert-primary mt-3 mb-0 py-2 px-3 small">
            <i class="ri-information-line me-1"></i>
            <strong>Neste momento:</strong> {{ $descricaoAtual }}
        </div>
    </div>
</div>

@once
    @push('head')
        <style>
            .captacao-timeline-steps {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem 0;
                padding: 0;
            }

            .captacao-timeline-step {
                display: flex;
                align-items: flex-start;
                gap: 0.5rem;
                flex: 1 1 12rem;
                min-width: 10rem;
                padding: 0.5rem 0.65rem;
                border-radius: 0.375rem;
                position: relative;
            }

            .captacao-timeline-step--concluido {
                background: var(--bs-success-bg-subtle, #d1e7dd);
            }

            .captacao-timeline-step--atual {
                background: var(--bs-primary-bg-subtle, #cfe2ff);
                box-shadow: inset 0 0 0 1px var(--bs-primary-border-subtle, #9ec5fe);
            }

            .captacao-timeline-step--pendente {
                background: var(--bs-light, #f8f9fa);
                opacity: 0.85;
            }

            .captacao-timeline-marker {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 1.5rem;
                height: 1.5rem;
                flex-shrink: 0;
                font-size: 1rem;
            }

            .captacao-timeline-step--concluido .captacao-timeline-marker {
                color: var(--bs-success);
            }

            .captacao-timeline-step--atual .captacao-timeline-marker {
                color: var(--bs-primary);
            }

            .captacao-timeline-step--pendente .captacao-timeline-marker {
                color: var(--bs-secondary);
            }

            .captacao-timeline-label {
                font-size: 0.8rem;
                font-weight: 600;
                line-height: 1.25;
            }

            @media (max-width: 767.98px) {
                .captacao-timeline-steps {
                    flex-direction: column;
                }

                .captacao-timeline-step {
                    flex: 1 1 auto;
                    width: 100%;
                }
            }
        </style>
    @endpush
@endonce
