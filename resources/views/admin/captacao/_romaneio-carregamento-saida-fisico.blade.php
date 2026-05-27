@php
    /** @var \App\Models\Captacao\CaptacaoLote $lote */
    /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $romaneioCarregamento */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Captacao\Pedido>|\Illuminate\Database\Eloquent\Collection $pedidosPorCliente */
    $idGalpao = (int) $lote->id_unidade_negocio_galpao;
    $idHub = $lote->id_unidade_negocio_hub_origem !== null ? (int) $lote->id_unidade_negocio_hub_origem : null;
    $nomeGalpao = $lote->unidadeGalpao?->nome ?? 'Galpão';
    $nomeHub = $lote->unidadeHubOrigem?->nome ?? 'HUB';
@endphp

<table class="table table-sm table-bordered align-middle mb-0" id="romaneio-saida-fisico-tabela">
    <thead>
    <tr>
        <th>Loja</th>
        <th class="text-nowrap" style="min-width:12rem">Saída física (venda)</th>
        <th>Rota</th>
        <th>Fruta</th>
        <th class="text-end">Qtd</th>
        <th>Unid. med.</th>
        <th class="text-end">Qtd (kg)</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($romaneioCarregamento as $loja)
        @php
            $pedido = $pedidosPorCliente->get($loja['id_cliente']);
            $saidaAtual = (int) ($pedido?->id_unidade_negocio_saida_venda ?? $idGalpao);
            $qtdLinhas = count($loja['itens']);
            $rowspan = $qtdLinhas + 1;
        @endphp
        @foreach ($loja['itens'] as $indice => $item)
            <tr>
                @if ($indice === 0)
                    <td rowspan="{{ $rowspan }}" class="fw-semibold text-nowrap align-top">{{ $loja['cliente_nome'] }}</td>
                    <td rowspan="{{ $rowspan }}" class="align-top">
                        <div class="d-flex flex-column gap-1 small" data-saida-fisica-loja="{{ $loja['id_cliente'] }}">
                            <label class="form-check mb-0">
                                <input type="radio"
                                       class="form-check-input captacao-saida-fisica-radio"
                                       name="saida_fisica_{{ $loja['id_cliente'] }}"
                                       value="{{ $idGalpao }}"
                                       data-cliente="{{ $loja['id_cliente'] }}"
                                       data-url="{{ route('admin.captacao.lotes.pedidos.saida-fisica-venda', [$lote, $loja['id_cliente']]) }}"
                                       @checked($saidaAtual === $idGalpao)>
                                <span class="form-check-label">{{ $nomeGalpao }}</span>
                            </label>
                            @if ($idHub !== null)
                                <label class="form-check mb-0">
                                    <input type="radio"
                                           class="form-check-input captacao-saida-fisica-radio"
                                           name="saida_fisica_{{ $loja['id_cliente'] }}"
                                           value="{{ $idHub }}"
                                           data-cliente="{{ $loja['id_cliente'] }}"
                                           data-url="{{ route('admin.captacao.lotes.pedidos.saida-fisica-venda', [$lote, $loja['id_cliente']]) }}"
                                           @checked($saidaAtual === $idHub)>
                                    <span class="form-check-label">{{ $nomeHub }}</span>
                                </label>
                            @else
                                <span class="text-muted">HUB não definido na aba Arquivo Cigam.</span>
                            @endif
                            <span class="captacao-saida-fisica-status text-muted" aria-live="polite"></span>
                        </div>
                    </td>
                    <td rowspan="{{ $rowspan }}" class="text-nowrap align-top">{{ $loja['rota_nome'] ?? '—' }}</td>
                @endif
                <td>{{ $item['fruta_nome'] }}</td>
                <td class="text-end">{{ $item['quantidade_um_formatado'] }}</td>
                <td><span class="badge bg-light text-dark">{{ $item['unidade_medicao'] }}</span></td>
                <td class="text-end">{{ $item['quantidade_kg_formatado'] }}</td>
            </tr>
        @endforeach
        <tr class="table-light">
            <td class="text-end fw-semibold">Total da loja</td>
            <td class="text-end fw-semibold">
                @foreach ($loja['totais_por_um'] as $totalUm)
                    <div>{{ $totalUm['quantidade_formatado'] }} {{ $totalUm['unidade_medicao'] }}</div>
                @endforeach
            </td>
            <td></td>
            <td class="text-end fw-semibold">{{ $loja['total_kg_formatado'] }}</td>
        </tr>
    @empty
        <tr><td colspan="7" class="text-muted">Sem pedidos com quantidade.</td></tr>
    @endforelse
    </tbody>
    @if (($romaneioCarregamentoTotaisGerais ?? null) && $romaneioCarregamento->isNotEmpty())
        <tfoot>
        <tr class="table-secondary fw-semibold">
            <td colspan="4" class="text-end">Total geral</td>
            <td class="text-end">
                @foreach ($romaneioCarregamentoTotaisGerais['totais_por_um'] as $totalUm)
                    <div>{{ $totalUm['quantidade_formatado'] }} {{ $totalUm['unidade_medicao'] }}</div>
                @endforeach
            </td>
            <td></td>
            <td class="text-end">{{ $romaneioCarregamentoTotaisGerais['total_kg_formatado'] }}</td>
        </tr>
        </tfoot>
    @endif
</table>
