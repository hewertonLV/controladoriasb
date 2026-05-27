@php
    /** @var list<array<string, mixed>> $gruposRotas */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Captacao\CaptacaoRota> $rotas */
    /** @var \App\Models\Captacao\CaptacaoLote $lote */
@endphp

@if ($gruposRotas === [])
    <tr>
        <td colspan="5" class="text-muted text-center py-4">
            Nenhum item com quantidade informada. Use a aba <strong>Captação</strong> para captar pedidos.
        </td>
    </tr>
@else
    @foreach ($gruposRotas as $grupo)
        @foreach ($grupo['itens'] as $idx => $item)
            <tr class="matriz-rotas-row"
                data-cliente-id="{{ $grupo['id_cliente'] }}"
                data-fruta-id="{{ $item['id_fruta'] }}">
                @if ($idx === 0)
                    <td rowspan="{{ count($grupo['itens']) }}" class="align-top text-nowrap">
                        <span class="fw-semibold">{{ $grupo['loja_nome'] }}</span>
                        @if (! empty($grupo['numero_pedido']))
                            <div class="text-muted small">Pedido {{ $grupo['numero_pedido'] }}</div>
                        @endif
                    </td>
                @endif
                <td>
                    {{ $item['fruta_nome'] }}
                    <span class="text-muted small">({{ $item['unidade_medicao'] }})</span>
                </td>
                <td class="text-end matriz-rotas-qty">
                    {{ rtrim(rtrim($item['quantidade'], '0'), '.') }}
                </td>
                <td class="text-end matriz-rotas-preco">
                    @if ($item['preco_venda'] !== null && (float) $item['preco_venda'] > 0)
                        {{ number_format((float) $item['preco_venda'], 2, ',', '.') }}
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                @if ($idx === 0)
                    <td rowspan="{{ count($grupo['itens']) }}" class="align-top">
                        @if ($lote->status->permiteEdicaoVinculoRota())
                            <select class="form-select form-select-sm matriz-rota-select"
                                    data-search-select
                                    data-placeholder="Selecione ou pesquise a rota"
                                    data-cliente="{{ $grupo['id_cliente'] }}"
                                    data-url="{{ route('admin.captacao.lotes.pedidos.rota', [$lote, $grupo['id_cliente']]) }}">
                                @if ($rotas->isEmpty())
                                    <option value="">Nenhuma rota nesta carteira</option>
                                @else
                                    <option value="">Selecione a rota…</option>
                                    @foreach ($rotas as $rota)
                                        <option value="{{ $rota->id }}"
                                                @selected((int) ($grupo['id_captacao_rota'] ?? 0) === $rota->id)>
                                            {{ $rota->nome }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                        @else
                            @php
                                $rotaNome = $rotas->firstWhere('id', $grupo['id_captacao_rota'])?->nome;
                            @endphp
                            {{ $rotaNome ?? '—' }}
                        @endif
                    </td>
                @endif
            </tr>
        @endforeach
    @endforeach
@endif
