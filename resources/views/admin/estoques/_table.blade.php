@php
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $isPaginator = $estoques instanceof LengthAwarePaginator;
    $linhas = $isPaginator ? $estoques->items() : $estoques;
@endphp

<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-centered table-hover mb-0">
            <thead class="bg-light bg-opacity-50">
                <tr>
                    <x-admin.sortable-th label="Unidade" sort="unidade" :filtros="$filtros" />
                    <x-admin.sortable-th label="Fruta" sort="fruta" :filtros="$filtros" />
                    <x-admin.sortable-th label="Qtd (kg)" sort="qtd_fruta_kg" :filtros="$filtros" />
                    <th>Qtd (UM)</th>
                    <x-admin.sortable-th label="Pço médio kg" sort="preco_medio_kg" :filtros="$filtros" />
                    <th>Pço médio UM</th>
                    <x-admin.sortable-th label="Valor total" sort="valor_total" :filtros="$filtros" />
                    <x-admin.sortable-th label="Atualizado" sort="created_at" :filtros="$filtros" />
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $estoque)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $estoque->unidadeNegocio->nome ?? '—' }}</span><br>
                            <code class="small">{{ $estoque->unidadeNegocio->id_cigam ?? '' }}</code>
                        </td>
                        <td>
                            {{ $estoque->fruta->nome ?? '—' }}<br>
                            <code class="small">{{ $estoque->fruta->id_cigam ?? '' }}</code>
                        </td>
                        <td>{{ number_format((float) $estoque->qtd_fruta_kg, 2, ',', '.') }}</td>
                        <td>{{ number_format((float) $estoque->qtd_fruta_um, 2, ',', '.') }} {{ $estoque->fruta->unidade_medicao ?? '' }}</td>
                        <td>R$ {{ number_format((float) $estoque->preco_medio_kg, 2, ',', '.') }}</td>
                        <td>R$ {{ number_format((float) $estoque->preco_medio_um, 2, ',', '.') }}</td>
                        <td>R$ {{ number_format((float) $estoque->valor_total_acumulado, 2, ',', '.') }}</td>
                        <td>{{ optional($estoque->updated_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            @can('estoques.visualizar')
                                <a href="{{ route('admin.estoques.show', $estoque) }}" class="btn btn-sm btn-soft-primary">
                                    <i class="ri-eye-line"></i> Detalhes
                                </a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">
                            @if (($filtros['search'] ?? '') !== '' || ($filtros['id_unidade_negocio'] ?? null) !== null || ($filtros['id_fruta'] ?? null) !== null)
                                Nenhum estoque corresponde aos filtros aplicados.
                            @else
                                Nenhuma posição de estoque registrada. Utilize movimentação ou importação.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card-footer d-flex flex-wrap align-items-center gap-2">
    <div class="text-muted small me-auto">
        Exibindo <strong>{{ $exibindo }}</strong> de <strong>{{ $total }}</strong> posição(ões).
    </div>
    <x-admin.table-pagination :paginator="$estoques" />
</div>
