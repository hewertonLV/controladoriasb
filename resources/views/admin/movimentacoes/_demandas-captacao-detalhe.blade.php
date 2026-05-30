@php
    /** @var array<string, mixed> $card */
    $demanda = $demanda ?? null;
    $acoes = $card['acoes'] ?? [];
    $cor = $card['cor_bootstrap'] ?? 'secondary';
@endphp

<div class="card shadow-sm mb-3">
    <div class="card-body p-3">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="badge bg-{{ $cor }}-subtle text-{{ $cor }}">{{ $card['tipo_label'] ?? '' }}</span>
            <span class="badge bg-light text-body-secondary border">{{ $card['status_label'] ?? '' }}</span>
        </div>
        <h5 class="mb-1">{{ $card['titulo'] ?? '' }}</h5>
        @if (! empty($card['subtitulo']))
            <div class="text-muted small mb-2">{{ $card['subtitulo'] }}</div>
        @endif
        @foreach ($card['detalhes'] ?? [] as $detalhe)
            <div class="small text-muted">{{ $detalhe }}</div>
        @endforeach
        @if (! empty($card['romaneio']))
            @php
                $resumoRomaneio = $card['romaneio_resumo'] ?? [];
            @endphp
            @if (! empty($resumoRomaneio['origem_transferencia']) || ! empty($resumoRomaneio['destino_faturamento']))
                <div class="alert alert-light border small py-2 mb-2">
                    <strong>Motivo da transferência (faturamento fiscal):</strong>
                    saída física da venda em
                    <strong>{{ $resumoRomaneio['origem_transferencia'] ?? '—' }}</strong>
                    → Unidade de faturamento
                    <strong>{{ $resumoRomaneio['destino_faturamento'] ?? '—' }}</strong>.
                    Essa movimentação será fiscal somente no CIGAM.
                </div>
            @endif
            <div class="table-responsive mt-2">
                <table class="table table-sm table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center" style="width:3rem">Ord.</th>
                            <th>Loja</th>
                            <th>Origem fiscal (saída venda)</th>
                            <th>Fruta</th>
                            <th class="text-end">Qtd</th>
                            <th class="text-end">Preço venda</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($card['romaneio'] as $bloco)
                            @foreach ($bloco['itens'] ?? [] as $idx => $item)
                                <tr>
                                    @if ($idx === 0)
                                        <td rowspan="{{ count($bloco['itens']) }}" class="text-center text-muted">
                                            {{ $bloco['ordem'] ?? '—' }}
                                        </td>
                                        <td rowspan="{{ count($bloco['itens']) }}" class="fw-semibold">
                                            {{ $bloco['loja_nome'] }}
                                        </td>
                                    @endif
                                    <td class="small">{{ $item['origem_fiscal_nome'] ?? '—' }}</td>
                                    <td>{{ $item['fruta_nome'] }}</td>
                                    <td class="text-end">{{ $item['qtd_formatada'] }}</td>
                                    <td class="text-end">
                                        @if (! empty($item['preco_venda']))
                                            R$ {{ $item['preco_venda'] }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                    @if (! empty($resumoRomaneio['totais_por_fruta']))
                        <tfoot>
                            @foreach ($resumoRomaneio['totais_por_fruta'] as $totalFruta)
                                <tr class="table-light fw-semibold">
                                    <td colspan="3" class="text-end">Total da demanda</td>
                                    <td>{{ $totalFruta['fruta_nome'] }}</td>
                                    <td class="text-end">{{ $totalFruta['qtd_formatada'] }}</td>
                                    <td></td>
                                </tr>
                            @endforeach
                        </tfoot>
                    @endif
                </table>
            </div>
        @endif
        @if (! empty($card['aviso_cigam']))
            <div class="small text-warning-emphasis mt-2">
                Demanda criada automaticamente para realizar no CIGAM, para efetivar a venda.
                Essa fruta não será transferida no SB Controladoria; servirá somente para FATURAMENTO FISCAL.
                @if (! empty($card['demanda_automatica_rota']))
                    Não pode ser excluída manualmente — use reabrir rota na matriz, se necessário.
                @endif
            </div>
        @endif
        <div class="d-flex flex-wrap gap-2 mt-3 captacao-demanda-acoes">
            @if (($card['tipo'] ?? '') === \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
                @if (! empty($acoes['pode_iniciar']))
                    <button type="button" class="btn btn-soft-primary btn-sm btn-demanda-iniciar-transferencia" data-url="{{ $acoes['url_iniciar'] }}">Iniciar transferência</button>
                @elseif (($card['status_demanda'] ?? '') === \App\Enums\CaptacaoDemandaStatus::Aberto->value)
                    <span class="small text-muted align-self-center">Inicie a transferência para baixar o arquivo Cigam.</span>
                @endif
                @if (! empty($acoes['pode_cigam']))
                    <a href="{{ $acoes['url_cigam'] }}" class="btn btn-soft-secondary btn-sm" download>
                        <i class="ri-download-2-line"></i> Baixar arquivo Cigam
                    </a>
                @endif
                @if (! empty($acoes['pode_nf']))
                    <button type="button" class="btn btn-soft-success btn-sm btn-demanda-anexar-nf" data-url="{{ $acoes['url_nf'] }}">Anexar NF</button>
                @endif
                @if (! empty($acoes['pode_excluir']))
                    <button type="button" class="btn btn-soft-danger btn-sm btn-demanda-excluir-transferencia" data-url="{{ $acoes['url_excluir'] }}">Excluir</button>
                @endif
            @elseif (($card['tipo'] ?? '') === \App\Models\Captacao\CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA)
                @if (! empty($acoes['pode_cigam']))
                    <a href="{{ $acoes['url_cigam'] }}" class="btn btn-soft-secondary btn-sm" download>CIGAM</a>
                @endif
                @if (! empty($acoes['pode_efetivar']))
                    <button type="button" class="btn btn-soft-success btn-sm btn-demanda-efetivar-venda" data-url="{{ $acoes['url_efetivar'] }}">Efetivar venda</button>
                @endif
                @if (! empty($card['url']))
                    <a href="{{ $card['url'] }}" class="btn btn-soft-secondary btn-sm" target="_blank" rel="noopener">Ver venda</a>
                @endif
            @endif
        </div>
    </div>
</div>
<input type="file" class="d-none captacao-demanda-nf-input" accept=".xml,.pdf,.txt">
