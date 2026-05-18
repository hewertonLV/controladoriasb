@php
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $isPaginator = $grupos instanceof LengthAwarePaginator;
    $linhas = $isPaginator ? $grupos->items() : $grupos;
@endphp

<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-centered table-hover mb-0">
            <thead class="bg-light bg-opacity-50">
                <tr>
                    <x-admin.sortable-th label="Nome" sort="nome" :filtros="$filtros" />
                    <x-admin.sortable-th label="Criado" sort="created_at" :filtros="$filtros" />
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $grupo)
                    <tr>
                        <td><span class="fw-semibold">{{ $grupo->nome }}</span></td>
                        <td>{{ optional($grupo->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                @can('grupos.editar')
                                    <a href="{{ route('admin.grupos.edit', $grupo) }}"
                                       class="btn btn-sm btn-soft-primary"
                                       title="Editar">
                                        <i class="ri-pencil-line"></i> Editar
                                    </a>
                                @endcan

                                @can('grupos.historico')
                                    <a href="{{ route('admin.grupos.historico', $grupo) }}"
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
                        <td colspan="3" class="text-center text-muted py-4">
                            @if (($filtros['search'] ?? '') !== '')
                                Nenhum grupo corresponde aos filtros aplicados.
                            @else
                                Nenhum grupo cadastrado.
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
        Exibindo <strong>{{ $exibindo }}</strong> de <strong>{{ $total }}</strong> grupo(s).
        @if (($filtros['search'] ?? '') !== '')
            · Pesquisa: <code>{{ $filtros['search'] }}</code>
        @endif
    </div>
    <x-admin.table-pagination :paginator="$grupos" />
</div>
