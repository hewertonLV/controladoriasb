@php
    /** @var \App\Models\Captacao\CaptacaoLote $lote */
    /** @var \Illuminate\Support\Collection<int, array{id_cliente: int, loja_nome: string, numero_nf: string, id_frete_atual: int|null, itens: list<array{fruta_nome: string, quantidade: string, unidade_medicao: string|null, preco_venda: string|null}>}> $lojas */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Frete> $fretesAbertos */
@endphp

<div class="card-body border-top-0 pt-4 pb-4" id="captacao-frete-vendas-root">
    <p class="text-muted small mb-3">
        Vendas geradas no lote. Frete é <strong>opcional</strong> por loja — ao escolher, salva automaticamente.
        Se vinculou por engano, use <strong>Remover Frete</strong>.
    </p>

    @forelse ($lojas as $loja)
        @php
            $idFreteAtual = $loja['id_frete_atual'] ?? null;
        @endphp
        <div class="border rounded p-3 mb-3">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div>
                    <h6 class="mb-1">{{ $loja['loja_nome'] }}</h6>
                    <span class="text-muted small">NF {{ $loja['numero_nf'] }}</span>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    @include('admin.captacao.matriz._frete-vinculo-campo', [
                        'url' => route('admin.captacao.lotes.fretes.venda-loja', $lote),
                        'fretesAbertos' => $fretesAbertos,
                        'idFreteAtual' => $idFreteAtual,
                        'selectStyle' => 'min-width: 14rem',
                        'placeholder' => 'Frete ABERTO (opcional)',
                        'dataAttrs' => [
                            'id-cliente' => $loja['id_cliente'],
                        ],
                    ])
                    <span class="captacao-frete-status small fw-semibold {{ $idFreteAtual ? 'text-success' : 'text-muted' }}">
                        {{ $idFreteAtual ? 'Vinculado' : 'Sem Frete' }}
                    </span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                    <tr>
                        <th>Fruta</th>
                        <th class="text-end">Qtd</th>
                        <th>UM</th>
                        <th class="text-end">Preço venda</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($loja['itens'] as $item)
                        <tr>
                            <td>{{ $item['fruta_nome'] }}</td>
                            <td class="text-end">{{ $item['quantidade'] }}</td>
                            <td>{{ $item['unidade_medicao'] ?? '—' }}</td>
                            <td class="text-end">
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
    @empty
        <p class="text-muted mb-0">Nenhuma venda com itens para vincular frete neste lote.</p>
    @endforelse
</div>

@include('admin.captacao.matriz._frete-vinculo-scripts')
