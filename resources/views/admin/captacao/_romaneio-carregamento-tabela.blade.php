@php
    /** @var \Illuminate\Support\Collection<int, array<string, mixed>>|list<array<string, mixed>> $romaneioCarregamento */
    /** @var array{total_kg_formatado: string, totais_por_um: list<array{unidade_medicao: string, quantidade_formatado: string}>}|null $romaneioCarregamentoTotaisGerais */
    $romaneioCarregamentoTotaisGerais ??= null;
@endphp

<table class="table table-sm table-bordered align-middle mb-0">
    <thead>
    <tr>
        <th>Loja</th>
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
            $qtdLinhas = count($loja['itens']);
            $rowspan = $qtdLinhas + 1;
        @endphp
        @foreach ($loja['itens'] as $indice => $item)
            <tr>
                @if ($indice === 0)
                    <td rowspan="{{ $rowspan }}" class="fw-semibold text-nowrap">{{ $loja['cliente_nome'] }}</td>
                    <td rowspan="{{ $rowspan }}" class="text-nowrap">{{ $loja['rota_nome'] ?? '—' }}</td>
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
        <tr><td colspan="6" class="text-muted">Sem pedidos.</td></tr>
    @endforelse
    </tbody>
    @if ($romaneioCarregamentoTotaisGerais && $romaneioCarregamento->isNotEmpty())
        <tfoot>
        <tr class="table-secondary fw-semibold">
            <td colspan="3" class="text-end">Total geral</td>
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
