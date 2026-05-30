@php
    /** @var \Illuminate\Support\Collection<int, array<string, mixed>>|list<array<string, mixed>> $romaneioCarregamento */
    /** @var array{total_kg_formatado: string, totais_por_um: list<array{unidade_medicao: string, quantidade_formatado: string}>}|null $romaneioCarregamentoTotaisGerais */
    $romaneioCarregamentoTotaisGerais ??= null;
    $exibirRota = $exibirRota ?? true;
    $colunas = $exibirRota ? 7 : 6;
@endphp

<table class="table table-sm table-bordered align-middle mb-0 captacao-romaneio-tabela">
    <thead>
    <tr>
        <th style="width:3.5rem">Ordem</th>
        <th>Loja</th>
        @if ($exibirRota)
            <th>Rota</th>
        @endif
        <th>Fruta</th>
        <th>Qtd</th>
        <th>Unid.</th>
        <th>Qtd (kg)</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($romaneioCarregamento as $lojaIndice => $loja)
        @php
            $qtdLinhas = count($loja['itens']);
            $rowspan = $qtdLinhas + 1;
            $classeCorLoja = ($lojaIndice % 2 === 0) ? 'captacao-romaneio-loja-par' : 'captacao-romaneio-loja-impar';
        @endphp
        @foreach ($loja['itens'] as $indice => $item)
            <tr class="captacao-romaneio-linha-item {{ $classeCorLoja }}">
                @if ($indice === 0)
                    <td rowspan="{{ $rowspan }}" class="text-center fw-semibold">
                        {{ $loja['ordem_carregamento'] ?? '—' }}
                    </td>
                    <td rowspan="{{ $rowspan }}" class="text-center fw-semibold captacao-romaneio-col-loja">
                        <span class="captacao-romaneio-loja-text">{{ $loja['cliente_nome'] }}</span>
                    </td>
                    @if ($exibirRota)
                        <td rowspan="{{ $rowspan }}" class="text-center">{{ $loja['rota_nome'] ?? '—' }}</td>
                    @endif
                @endif
                <td class="captacao-romaneio-col-fruta">{{ $item['fruta_nome'] }}</td>
                <td class="text-end">{{ $item['quantidade_um_formatado'] }}</td>
                <td class="text-center">{{ $item['unidade_medicao'] }}</td>
                <td class="text-end">{{ $item['quantidade_kg_formatado'] }}</td>
            </tr>
        @endforeach
        <tr class="captacao-romaneio-linha-total {{ $classeCorLoja }}">
            <td class="text-end fw-semibold">Total da loja</td>
            <td class="text-end fw-semibold">
                @foreach ($loja['totais_por_um'] as $totalUm)
                    <div class="captacao-romaneio-total-um-linha">{{ $totalUm['quantidade_formatado'] }} {{ $totalUm['unidade_medicao'] }}</div>
                @endforeach
            </td>
            <td></td>
            <td class="text-end fw-semibold">{{ $loja['total_kg_formatado'] }}</td>
        </tr>
    @empty
        <tr><td colspan="{{ $colunas }}" class="text-muted text-center">Sem pedidos.</td></tr>
    @endforelse
    </tbody>
    @if ($romaneioCarregamentoTotaisGerais && $romaneioCarregamento->isNotEmpty())
        <tfoot>
        <tr class="fw-semibold captacao-romaneio-linha-total">
            <td colspan="{{ $exibirRota ? 4 : 3 }}" class="text-end">Total geral</td>
            <td class="text-end">
                @foreach ($romaneioCarregamentoTotaisGerais['totais_por_um'] as $totalUm)
                    <div class="captacao-romaneio-total-um-linha">{{ $totalUm['quantidade_formatado'] }} {{ $totalUm['unidade_medicao'] }}</div>
                @endforeach
            </td>
            <td></td>
            <td class="text-end">{{ $romaneioCarregamentoTotaisGerais['total_kg_formatado'] }}</td>
        </tr>
        </tfoot>
    @endif
</table>
