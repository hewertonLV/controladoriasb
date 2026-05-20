@php
    /** @var list<array{fruta: \App\Models\Fruta, estado: \App\Models\Estado, valores: array<string, string>}> $linhas */
@endphp

<div class="card-body">
    <table id="frutas-icms-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
        <thead>
            <tr>
                <th>Fruta</th>
                <th>Es</th>
                <th>E. nac. kg</th>
                <th>E. int. kg</th>
                <th>V. nac. D.</th>
                <th>V. nac. F.</th>
                <th>V. int. D.</th>
                <th>V. int. F.</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $item)
                @php($v = $item['valores'])
                <tr>
                    <td>
                        <code class="small">{{ $item['fruta']->id_cigam }}</code>
                        <span class="fw-semibold d-block">{{ $item['fruta']->nome }}</span>
                        <span class="badge bg-light text-muted">{{ $item['fruta']->procedencia }}</span>
                    </td>
                    <td>
                        <span class="fw-semibold">{{ $item['estado']->abreviacao }}</span>
                    </td>
                    <td>{{ number_format((float) $v['entrada_nacional_kg'], 2, ',', '.') }} R$</td>
                    <td>{{ number_format((float) $v['entrada_internacional_kg'], 2, ',', '.') }} R$</td>
                    <td>{{ number_format((float) $v['saida_nacional_dentro_pct'], 2, ',', '.') }} %</td>
                    <td>{{ number_format((float) $v['saida_nacional_fora_pct'], 2, ',', '.') }} %</td>
                    <td>{{ number_format((float) $v['saida_internacional_dentro_pct'], 2, ',', '.') }} %</td>
                    <td>{{ number_format((float) $v['saida_internacional_fora_pct'], 2, ',', '.') }} %</td>
                    <td class="text-end">
                        @can('frutas.icms.editar')
                            <a href="{{ route('admin.frutas.icms.edit', [$item['fruta'], $item['estado']]) }}"
                               class="btn btn-sm btn-soft-primary" title="Editar">
                                <i class="ri-pencil-line"></i>
                            </a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">Nenhum ICMS cadastrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
