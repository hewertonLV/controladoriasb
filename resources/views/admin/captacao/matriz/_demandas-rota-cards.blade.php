@php
    /** @var list<array<string, mixed>> $demandas */
    $demandas = $demandas ?? [];
@endphp

@if ($demandas !== [])
    <div class="matriz-rota-demandas mt-2">
        <div class="small fw-semibold text-muted mb-1">Demandas geradas</div>
        <div class="row g-2">
            @foreach ($demandas as $demanda)
                @php
                    $estadoClasse = 'estado-' . ($demanda['estado_classe'] ?? 'nao_iniciado');
                    $cor = $demanda['cor_bootstrap'] ?? 'secondary';
                    $acoes = $demanda['acoes'] ?? [];
                @endphp
                <div class="col-12">
                    <div class="card captacao-demanda-card {{ $estadoClasse }} shadow-sm" data-demanda-id="{{ $demanda['id'] }}">
                        <div class="card-body p-2">
                            <div class="d-flex align-items-start gap-2">
                                <div class="captacao-demanda-card__icone bg-{{ $cor }}-subtle text-{{ $cor }} rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
                                    <i class="{{ $demanda['icone'] ?? 'ri-file-list-3-line' }}"></i>
                                </div>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex flex-wrap align-items-center gap-1 mb-1">
                                        <span class="badge bg-{{ $cor }}-subtle text-{{ $cor }}">{{ $demanda['tipo_label'] }}</span>
                                        <span class="badge bg-light text-body-secondary border">{{ $demanda['status_label'] }}</span>
                                    </div>
                                    <div class="fw-semibold text-truncate" title="{{ $demanda['titulo'] }}">{{ $demanda['titulo'] }}</div>
                                    @if (! empty($demanda['subtitulo']))
                                        <div class="small text-muted text-truncate">{{ $demanda['subtitulo'] }}</div>
                                    @endif
                                    @foreach ($demanda['detalhes'] ?? [] as $detalhe)
                                        <div class="small text-muted">{{ $detalhe }}</div>
                                    @endforeach
                                    @if (! empty($demanda['aviso_cigam']))
                                        <div class="small text-warning-emphasis mt-2 captacao-demanda-aviso-cigam">
                                            Demanda criada automaticamente para realizar no CIGAM, para efetivar a venda.
                                            Essa fruta não será transferida no SB Controladoria; servirá somente para FATURAMENTO FISCAL.
                                        </div>
                                    @endif
                                    <div class="d-flex flex-wrap gap-1 mt-2 captacao-demanda-acoes">
                                        @if (($demanda['tipo'] ?? '') === \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
                                            @if (! empty($acoes['pode_iniciar']))
                                                <button type="button" class="btn btn-soft-primary btn-sm btn-demanda-iniciar-transferencia" data-url="{{ $acoes['url_iniciar'] }}">Iniciar</button>
                                            @endif
                                            @if (! empty($acoes['pode_cigam']))
                                                <a href="{{ $acoes['url_cigam'] }}" class="btn btn-soft-secondary btn-sm" download>CIGAM</a>
                                            @endif
                                            @if (! empty($acoes['pode_nf']))
                                                <button type="button" class="btn btn-soft-success btn-sm btn-demanda-anexar-nf" data-url="{{ $acoes['url_nf'] }}">Anexar NF</button>
                                            @endif
                                            @if (! empty($acoes['pode_excluir']))
                                                <button type="button" class="btn btn-soft-danger btn-sm btn-demanda-excluir-transferencia" data-url="{{ $acoes['url_excluir'] }}">Excluir</button>
                                            @endif
                                        @elseif (($demanda['tipo'] ?? '') === \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA)
                                            @if (! empty($acoes['pode_efetivar']))
                                                <button type="button" class="btn btn-soft-success btn-sm btn-demanda-efetivar-venda" data-url="{{ $acoes['url_efetivar'] }}">Efetivar venda</button>
                                            @endif
                                            @if (! empty($demanda['url']))
                                                <a href="{{ $demanda['url'] }}" class="btn btn-soft-secondary btn-sm" target="_blank" rel="noopener">Ver venda</a>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <input type="file" class="d-none captacao-demanda-nf-input" accept=".xml,.pdf,.txt">
    </div>
@endif
