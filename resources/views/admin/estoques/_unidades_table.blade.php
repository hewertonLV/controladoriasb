@php
    /** @var \Illuminate\Support\Collection<int, object>|iterable<int, object> $unidades */
    $linhas = $unidades;
@endphp

<div class="card-body">
    <table id="estoques-unidades-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
        <thead>
            <tr>
                <th>Unidade</th>
                <th>CIGAM</th>
                <th>Frutas</th>
                <th>Kg</th>
                <th>Valor</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $unidade)
                <tr>
                    <td><span class="fw-semibold">{{ $unidade->nome }}</span></td>
                    <td data-order="{{ (int) ($unidade->id_cigam ?? 0) }}">
                        <code class="small">{{ $unidade->id_cigam ?: '—' }}</code>
                    </td>
                    <td data-order="{{ (int) $unidade->posicoes_count }}">{{ (int) $unidade->posicoes_count }}</td>
                    <td data-order="{{ (float) $unidade->total_kg }}">{{ number_format((float) $unidade->total_kg, 2, ',', '.') }}</td>
                    <td data-order="{{ (float) $unidade->valor_total }}">R$ {{ number_format((float) $unidade->valor_total, 2, ',', '.') }}</td>
                    <td class="text-end">
                        <a href="{{ route('admin.estoques.unidade', $unidade) }}"
                           class="btn btn-sm btn-primary">
                            <i class="ri-arrow-right-line me-1"></i> Abrir
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">Nenhuma unidade com estoque cadastrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
