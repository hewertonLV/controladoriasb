@php
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $isPaginator = $pracas instanceof LengthAwarePaginator;
    $linhas = $isPaginator ? $pracas->items() : $pracas;
@endphp

<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-centered table-hover mb-0">
            <thead class="bg-light bg-opacity-50">
                <tr>
                    <x-admin.sortable-th label="Nome" sort="nome" :filtros="$filtros" />
                    <x-admin.sortable-th label="Unidade de negócio" sort="id_unidade_negocio" :filtros="$filtros" />
                    <x-admin.sortable-th label="Criado em" sort="created_at" :filtros="$filtros" />
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $praca)
                    <tr>
                        <td><span class="fw-semibold">{{ $praca->nome }}</span></td>
                        <td>
                            @if ($praca->unidadeNegocio)
                                <span title="ID {{ $praca->id_unidade_negocio }}">
                                    {{ $praca->unidadeNegocio->nome }}
                                    <small class="text-muted">({{ $praca->unidadeNegocio->id_cigam }})</small>
                                </span>
                            @else
                                <code>{{ $praca->id_unidade_negocio }}</code>
                            @endif
                        </td>
                        <td>{{ optional($praca->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                @can('pracas.editar')
                                    <a href="{{ route('admin.pracas.edit', $praca) }}"
                                       class="btn btn-sm btn-soft-primary"
                                       title="Editar">
                                        <i class="ri-pencil-line"></i> Editar
                                    </a>
                                @endcan

                                @can('pracas.historico')
                                    <a href="{{ route('admin.pracas.historico', $praca) }}"
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
                        <td colspan="4" class="text-center text-muted py-4">
                            @if (($filtros['search'] ?? '') !== '')
                                Nenhuma praça corresponde aos filtros aplicados.
                            @else
                                Nenhuma praça cadastrada.
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
        Exibindo <strong>{{ $exibindo }}</strong> de <strong>{{ $total }}</strong> praça(s).
        @if (($filtros['search'] ?? '') !== '')
            · Pesquisa: <code>{{ $filtros['search'] }}</code>
        @endif
    </div>
    <x-admin.table-pagination :paginator="$pracas" />
</div>
