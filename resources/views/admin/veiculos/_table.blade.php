@php
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $isPaginator = $veiculos instanceof LengthAwarePaginator;
    $linhas = $isPaginator ? $veiculos->items() : $veiculos;
@endphp

<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-centered table-hover mb-0">
            <thead class="bg-light bg-opacity-50">
                <tr>
                    <x-admin.sortable-th label="ID SBS" sort="id_sbs" :filtros="$filtros" />
                    <x-admin.sortable-th label="Nome" sort="nome" :filtros="$filtros" />
                    <x-admin.sortable-th label="Tipo" sort="tipo" :filtros="$filtros" />
                    <x-admin.sortable-th label="Status" sort="status" :filtros="$filtros" />
                    <x-admin.sortable-th label="Criado em" sort="created_at" :filtros="$filtros" />
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $veiculo)
                    <tr class="{{ $veiculo->status === 'ATIVO' ? '' : 'text-muted bg-light bg-opacity-25' }}">
                        <td><code>{{ $veiculo->id_sbs }}</code></td>
                        <td><span class="fw-semibold">{{ $veiculo->nome }}</span></td>
                        <td>{{ $veiculo->tipo }}</td>
                        <td>
                            @if ($veiculo->status === 'ATIVO')
                                <span class="badge bg-success-subtle text-success">
                                    <i class="ri-checkbox-circle-line me-1"></i>Ativo
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">
                                    <i class="ri-close-circle-line me-1"></i>Inativo
                                </span>
                            @endif
                        </td>
                        <td>{{ optional($veiculo->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                @can('veiculos.editar')
                                    <a href="{{ route('admin.veiculos.edit', $veiculo) }}"
                                       class="btn btn-sm btn-soft-primary"
                                       title="Editar">
                                        <i class="ri-pencil-line"></i> Editar
                                    </a>
                                @endcan

                                @can('veiculos.historico')
                                    <a href="{{ route('admin.veiculos.historico', $veiculo) }}"
                                       class="btn btn-sm btn-soft-info"
                                       title="Histórico">
                                        <i class="ri-history-line"></i> Histórico
                                    </a>
                                @endcan

                                @if ($veiculo->status === 'ATIVO')
                                    @can('veiculos.inativar')
                                        <form method="POST"
                                              action="{{ route('admin.veiculos.inativar', $veiculo) }}"
                                              onsubmit="return confirm('Inativar o veículo {{ $veiculo->nome }}?');"
                                              class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-soft-secondary" title="Inativar">
                                                <i class="ri-close-circle-line"></i> Inativar
                                            </button>
                                        </form>
                                    @endcan
                                @else
                                    @can('veiculos.reativar')
                                        <form method="POST"
                                              action="{{ route('admin.veiculos.reativar', $veiculo) }}"
                                              onsubmit="return confirm('Reativar o veículo {{ $veiculo->nome }}?');"
                                              class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-soft-success" title="Reativar">
                                                <i class="ri-checkbox-circle-line"></i> Reativar
                                            </button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            @if (($filtros['search'] ?? '') !== '' || ($filtros['status'] ?? null) !== null)
                                Nenhum veículo corresponde aos filtros aplicados.
                            @else
                                Nenhum veículo cadastrado.
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
        Exibindo <strong>{{ $exibindo }}</strong> de <strong>{{ $total }}</strong> veículo(s).
        @if (($filtros['search'] ?? '') !== '')
            · Pesquisa: <code>{{ $filtros['search'] }}</code>
        @endif
        @if (($filtros['status'] ?? null) !== null)
            · Status: <code>{{ $filtros['status'] }}</code>
        @endif
    </div>
    <x-admin.table-pagination :paginator="$veiculos" />
</div>

