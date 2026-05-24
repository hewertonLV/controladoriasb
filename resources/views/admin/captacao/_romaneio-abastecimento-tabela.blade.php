@php
    /** @var \Illuminate\Support\Collection<int, array<string, mixed>>|list<array<string, mixed>> $romaneioAbastecimento */
@endphp

<table class="table table-sm">
    <thead>
    <tr>
        <th>Fruta</th>
        <th>Unid. med.</th>
        <th class="text-end">Demanda</th>
        <th class="text-end">Estoque</th>
        <th class="text-end">A receber</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($romaneioAbastecimento as $linha)
        <tr>
            <td>{{ $linha['fruta_nome'] }}</td>
            <td><span class="badge bg-light text-dark">{{ $linha['unidade_medicao'] }}</span></td>
            <td class="text-end">
                <div>{{ $linha['demanda_kg_formatado'] }} kg</div>
                <div class="text-muted small">{{ $linha['demanda_um_formatado'] }} {{ $linha['unidade_medicao'] }}</div>
            </td>
            <td class="text-end">
                <div>{{ $linha['estoque_kg_formatado'] }} kg</div>
                <div class="text-muted small">{{ $linha['estoque_um_formatado'] }} {{ $linha['unidade_medicao'] }}</div>
            </td>
            <td class="text-end">
                <div class="fw-semibold">{{ $linha['a_receber_kg_formatado'] }} kg</div>
                <div class="text-muted small">{{ $linha['a_receber_um_formatado'] }} {{ $linha['unidade_medicao'] }}</div>
            </td>
        </tr>
    @empty
        <tr><td colspan="5" class="text-muted">Sem demanda.</td></tr>
    @endforelse
    </tbody>
</table>
