@php
    /** @var list<array<string, mixed>> $demandas */
    $demandas = $demandas ?? [];
@endphp

@if ($demandas !== [])
    <div class="mb-4">
        <h5 class="mb-3">{{ $titulo ?? 'Demandas pendentes' }}</h5>
        <div class="row g-3">
            @foreach ($demandas as $demanda)
                @php
                    $estadoClasse = 'estado-' . ($demanda['estado_classe'] ?? 'nao_iniciado');
                    $url = $demanda['url_show'] ?? $demanda['url'] ?? null;
                @endphp
                <div class="col-sm-6 col-md-4 col-lg-3">
                    @if ($url)
                        <a href="{{ $url }}"
                           class="card captacao-loja-card {{ $estadoClasse }} shadow-sm">
                    @else
                        <div class="card captacao-loja-card {{ $estadoClasse }} shadow-sm">
                    @endif
                        <div class="card-body p-3">
                            <div class="small text-muted mb-1">{{ $demanda['tipo_label'] ?? 'Demanda' }}</div>
                            <div class="fw-semibold text-truncate" title="{{ $demanda['titulo'] ?? '' }}">
                                {{ $demanda['titulo'] ?? 'Demanda' }}
                            </div>
                            @if (! empty($demanda['subtitulo']))
                                <div class="small text-muted mt-1 text-truncate" title="{{ $demanda['subtitulo'] }}">
                                    {{ $demanda['subtitulo'] }}
                                </div>
                            @endif
                            <div class="small mt-2 {{ ($demanda['estado_classe'] ?? '') === 'concluido' ? 'text-success' : (($demanda['estado_classe'] ?? '') === 'em_andamento' ? 'text-warning' : 'text-muted') }}">
                                {{ $demanda['status_label'] ?? '' }}
                            </div>
                        </div>
                    @if ($url)
                        </a>
                    @else
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
