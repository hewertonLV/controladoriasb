@php
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $isPaginator = $frutas instanceof LengthAwarePaginator;
    $linhas = $isPaginator ? $frutas->items() : $frutas;
@endphp

<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-centered table-hover mb-0">
            <thead class="bg-light bg-opacity-50">
                <tr>
                    <x-admin.sortable-th label="# CI." sort="id_cigam" :filtros="$filtros" />
                    <x-admin.sortable-th label="Nome" sort="nome" :filtros="$filtros" />
                    <x-admin.sortable-th label="UM" sort="unidade_medicao" :filtros="$filtros" />
                    <x-admin.sortable-th label="Kg/UM" sort="kg_por_unidade_medicao" :filtros="$filtros" />
                    <th>ICMS ex.</th>
                    <th>ICMS ent.</th>
                    <th>UM ICMS</th>
                    <th>ICMS venda</th>
                    <x-admin.sortable-th label="Criado" sort="created_at" :filtros="$filtros" />
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $fruta)
                    <tr>
                        <td><code>{{ $fruta->id_cigam }}</code></td>
                        <td><span class="fw-semibold">{{ $fruta->nome }}</span></td>
                        <td>{{ $fruta->unidade_medicao }}</td>
                        <td>{{ number_format((float) $fruta->kg_por_unidade_medicao, 2, ',', '.') }}</td>
                        <td>{{ number_format((float) $fruta->icms_ex_compra, 2, ',', '.') }}</td>
                        <td>{{ number_format((float) $fruta->icms_na_compra, 2, ',', '.') }}</td>
                        <td><code>{{ $fruta->um_icms }}</code></td>
                        <td>{{ number_format((float) $fruta->icms_venda, 2, ',', '.') }}</td>
                        <td>{{ optional($fruta->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                @can('frutas.editar')
                                    <a href="{{ route('admin.frutas.edit', $fruta) }}"
                                       class="btn btn-sm btn-soft-primary"
                                       title="Editar">
                                        <i class="ri-pencil-line"></i> Editar
                                    </a>
                                @endcan

                                @can('frutas.historico')
                                    <a href="{{ route('admin.frutas.historico', $fruta) }}"
                                       class="btn btn-sm btn-soft-info"
                                       title="Histórico">
                                        <i class="ri-history-line"></i> Histórico
                                    </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">
                            @if (($filtros['search'] ?? '') !== '')
                                Nenhuma fruta corresponde aos filtros aplicados.
                            @else
                                Nenhuma fruta cadastrada.
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
        Exibindo <strong>{{ $exibindo }}</strong> de <strong>{{ $total }}</strong> fruta(s).
        @if (($filtros['search'] ?? '') !== '')
            · Pesquisa: <code>{{ $filtros['search'] }}</code>
        @endif
    </div>
    <x-admin.table-pagination :paginator="$frutas" />
</div>
