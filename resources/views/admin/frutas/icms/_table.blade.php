@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\FrutaIcms> $registros */
    /** @var \Illuminate\Support\Collection<string, \App\Models\FrutaIcms> $saidas */
@endphp

<div class="card-body">
    <table id="frutas-icms-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
        <thead>
            <tr>
                <th>Fruta</th>
                <th>Estado</th>
                <th>Compra nac.</th>
                <th>Compra ext.</th>
                <th>Venda imp.</th>
                <th>Venda nac.</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($registros as $entrada)
                @php
                    $chave = $entrada->fruta_id.'-'.$entrada->id_estado;
                    $saida = $saidas[$chave] ?? null;
                @endphp
                <tr>
                    <td>
                        <code class="small">{{ $entrada->fruta?->id_cigam }}</code>
                        <span class="fw-semibold d-block">{{ $entrada->fruta?->nome }}</span>
                    </td>
                    <td>
                        <span class="fw-semibold">{{ $entrada->estado?->nome }}</span>
                        <span class="badge bg-light text-muted">{{ $entrada->estado?->abreviacao }}</span>
                    </td>
                    <td>
                        {{ number_format((float) $entrada->icms_nacional, 2, ',', '.') }}
                        <span class="text-muted small">{{ $entrada->um_icms_nacional }}</span>
                    </td>
                    <td>
                        {{ number_format((float) $entrada->icms_externo, 2, ',', '.') }}
                        <span class="text-muted small">{{ $entrada->um_icms_externo }}</span>
                    </td>
                    <td>
                        @if ($saida)
                            {{ number_format((float) $saida->icms_venda_importada, 2, ',', '.') }}
                            <span class="text-muted small">{{ $saida->um_icms_venda_importada }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if ($saida)
                            {{ number_format((float) $saida->icms_venda_nacional, 2, ',', '.') }}
                            <span class="text-muted small">{{ $saida->um_icms_venda_nacional }}</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="text-end">
                        @can('frutas.icms.editar')
                            <a href="{{ route('admin.frutas.icms.edit', [$entrada->fruta_id, $entrada->id_estado]) }}"
                               class="admin-datatable-action-link text-primary"
                               title="Editar">
                                <i class="ri-pencil-line"></i>
                            </a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">Nenhum ICMS cadastrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
