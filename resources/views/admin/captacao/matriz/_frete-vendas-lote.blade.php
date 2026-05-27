@php
    /** @var \App\Models\Captacao\CaptacaoLote $lote */
    /** @var \Illuminate\Support\Collection<int, array{id_cliente: int, loja_nome: string, numero_nf: string, id_frete_atual: int|null, frete_nome: string|null, is_saida_hub: bool, saida_fisica_nome: string, saida_fisica_tipo: string, itens: list<array{fruta_nome: string, quantidade: string, unidade_medicao: string|null, preco_venda: string|null}>}> $lojas */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Frete> $fretesAbertos */
    $freteVendaEditavel = $freteVendaEditavel ?? false;
@endphp

<div class="card-body border-top-0 pt-3 pb-3" id="captacao-frete-vendas-root">
    <p class="text-muted small mb-2">
        @if ($freteVendaEditavel)
            Frete <strong>opcional</strong> por loja. HUB primeiro (borda azul); galpão em seguida (borda cinza).
            Conclua com <strong>Concluir frete venda</strong> no topo do lote.
        @else
            Frete de vendas <strong>bloqueado</strong> após a conclusão da etapa. Somente <strong>administrador</strong> pode alterar ou remover.
        @endif
    </p>

    @if ($lojas->isEmpty())
        <p class="text-muted mb-0 small">Nenhuma venda com itens para vincular frete neste lote.</p>
    @else
        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-2">
            @foreach ($lojas as $loja)
                @php
                    $idFreteAtual = $loja['id_frete_atual'] ?? null;
                    $isHub = (bool) ($loja['is_saida_hub'] ?? false);
                    $cardBorderClass = $isHub
                        ? 'border-primary border-2 captacao-frete-venda-card--hub'
                        : 'border-secondary captacao-frete-venda-card--galpao';
                @endphp
                <div class="col d-flex">
                    <div class="card {{ $cardBorderClass }} flex-fill h-100 mb-0 shadow-sm">
                        <div class="card-body p-2 d-flex flex-column gap-2">
                            <div class="d-flex flex-wrap align-items-center gap-2 captacao-frete-venda-card__header">
                                <div class="min-w-0 flex-grow-1" style="min-width: 0;">
                                    <div class="fw-semibold small text-truncate lh-sm mb-0"
                                         title="{{ $loja['loja_nome'] }}">{{ $loja['loja_nome'] }}</div>
                                    <div class="mt-1">
                                        <span class="text-muted d-block" style="font-size: 0.7rem;">NF {{ $loja['numero_nf'] }}</span>
                                        <span class="badge rounded-pill py-0 mt-1 {{ $isHub ? 'bg-primary-subtle text-primary' : 'bg-secondary-subtle text-secondary' }}"
                                              style="font-size: 0.65rem;">
                                            Saída: {{ $loja['saida_fisica_nome'] ?? ($isHub ? 'HUB' : 'Galpão') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap align-items-center gap-1 flex-shrink-0 ms-auto captacao-frete-venda-card__frete">
                                    @if ($freteVendaEditavel)
                                        @include('admin.captacao.matriz._frete-vinculo-campo', [
                                            'url' => route('admin.captacao.lotes.fretes.venda-loja', $lote),
                                            'fretesAbertos' => $fretesAbertos,
                                            'idFreteAtual' => $idFreteAtual,
                                            'selectClass' => 'form-select form-select-sm captacao-frete-select captacao-frete-venda-select',
                                            'selectStyle' => 'width: 9.5rem; min-width: 8rem; max-width: 11rem',
                                            'placeholder' => 'Frete',
                                            'dataAttrs' => [
                                                'id-cliente' => $loja['id_cliente'],
                                            ],
                                        ])
                                    @else
                                        <span class="text-muted text-nowrap" style="font-size: 0.75rem;" title="Somente administrador pode alterar">
                                            {{ $loja['frete_nome'] ?? 'Sem frete' }}
                                        </span>
                                    @endif
                                    <span class="captacao-frete-status text-nowrap {{ $idFreteAtual ? 'text-success' : 'text-muted' }}"
                                          style="font-size: 0.7rem;">
                                        {{ $idFreteAtual ? 'Vinculado' : 'Sem frete' }}
                                    </span>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0 captacao-frete-venda-card__itens">
                                    <thead class="table-light">
                                    <tr>
                                        <th>Fruta</th>
                                        <th class="text-end">Qtd</th>
                                        <th class="text-end">Preço</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach ($loja['itens'] as $item)
                                        <tr>
                                            <td class="text-truncate" title="{{ $item['fruta_nome'] }}">{{ $item['fruta_nome'] }}</td>
                                            <td class="text-end text-nowrap">{{ $item['quantidade'] }} {{ $item['unidade_medicao'] ?? '' }}</td>
                                            <td class="text-end text-nowrap">
                                                @if ($item['preco_venda'] !== null)
                                                    R$ {{ $item['preco_venda'] }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<style>
    #captacao-frete-vendas-root .captacao-frete-venda-card__itens {
        font-size: 0.75rem;
    }
    #captacao-frete-vendas-root .captacao-frete-venda-card__itens th,
    #captacao-frete-vendas-root .captacao-frete-venda-card__itens td {
        padding: 0.2rem 0.35rem;
    }
    #captacao-frete-vendas-root .captacao-frete-venda-card__frete .captacao-frete-vinculo {
        gap: 0.35rem !important;
    }
    #captacao-frete-vendas-root .captacao-frete-venda-card__frete .captacao-frete-remover {
        font-size: 0.7rem;
    }
    #captacao-frete-vendas-root .captacao-frete-venda-select + .select2-container {
        width: 9.5rem !important;
        min-width: 8rem;
        max-width: 11rem;
    }
    #captacao-frete-vendas-root .captacao-frete-venda-select + .select2-container .select2-selection--single {
        min-height: 1.75rem;
        font-size: 0.8rem;
    }
    #captacao-frete-vendas-root .captacao-frete-venda-select + .select2-container .select2-selection__rendered {
        line-height: 1.5rem;
        padding-left: 0.4rem;
    }
</style>

@include('admin.captacao.matriz._frete-vinculo-scripts')
