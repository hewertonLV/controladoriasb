@php
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $isPaginator = $unidadesNegocio instanceof LengthAwarePaginator;
    $linhas = $isPaginator ? $unidadesNegocio->items() : $unidadesNegocio;
    /** @var \Illuminate\Support\Collection<int, \App\Models\Estado>|null $estados */
    $estados = $estados ?? collect();
@endphp

<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-centered table-hover mb-0">
            <thead class="bg-light bg-opacity-50">
                <tr>
                    <x-admin.sortable-th label="ID CIGAM" sort="id_cigam" :filtros="$filtros" />
                    <x-admin.sortable-th label="Estado (ICMS)" sort="estado" :filtros="$filtros" />
                    <x-admin.sortable-th label="Razão social" sort="razao_social" :filtros="$filtros" />
                    <x-admin.sortable-th label="Nome" sort="nome" :filtros="$filtros" />
                    <x-admin.sortable-th label="CPF/CNPJ" sort="cpf_cnpj" :filtros="$filtros" />
                    <x-admin.sortable-th label="Custo op." sort="custo_operacional" :filtros="$filtros" />
                    <x-admin.sortable-th label="Estoque" sort="possui_estoque" :filtros="$filtros" />
                    <x-admin.sortable-th label="Status" sort="status" :filtros="$filtros" />
                    <x-admin.sortable-th label="Criado em" sort="created_at" :filtros="$filtros" />
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $unidade)
                    <tr class="{{ $unidade->status ? '' : 'text-muted bg-light bg-opacity-25' }}">
                        <td><code>{{ $unidade->id_cigam }}</code></td>
                        <td>{{ $unidade->estado?->nome ?? '—' }}</td>
                        <td><span class="fw-semibold">{{ $unidade->razao_social }}</span></td>
                        <td>{{ $unidade->nome }}</td>
                        <td><code>{{ $unidade->cpf_cnpj_formatado }}</code></td>
                        <td>{{ number_format((float) $unidade->custo_operacional, 2, ',', '.') }}</td>
                        <td>
                            @if ($unidade->possui_estoque)
                                <span class="badge bg-info-subtle text-info">Sim</span>
                            @else
                                <span class="badge bg-light text-muted border">Não</span>
                            @endif
                        </td>
                        <td>
                            @if ($unidade->status)
                                <span class="badge bg-success-subtle text-success">
                                    <i class="ri-checkbox-circle-line me-1"></i>Ativa
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">
                                    <i class="ri-close-circle-line me-1"></i>Inativa
                                </span>
                            @endif
                        </td>
                        <td>{{ optional($unidade->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                @can('unidades-negocio.editar')
                                    <a href="{{ route('admin.unidades-negocio.edit', $unidade) }}"
                                       class="btn btn-sm btn-soft-primary"
                                       title="Editar">
                                        <i class="ri-pencil-line"></i> Editar
                                    </a>
                                @endcan

                                @can('unidades-negocio.historico')
                                    <a href="{{ route('admin.unidades-negocio.historico', $unidade) }}"
                                       class="btn btn-sm btn-soft-info"
                                       title="Histórico">
                                        <i class="ri-history-line"></i> Histórico
                                    </a>
                                @endcan

                                @if ($unidade->status)
                                    @can('unidades-negocio.inativar')
                                        <form method="POST"
                                              action="{{ route('admin.unidades-negocio.inativar', $unidade) }}"
                                              onsubmit="return confirm({{ json_encode('Inativar a Unidade de Negócio '.$unidade->nome.'?') }});"
                                              class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-soft-secondary" title="Inativar">
                                                <i class="ri-close-circle-line"></i> Inativar
                                            </button>
                                        </form>
                                    @endcan
                                @else
                                    @can('unidades-negocio.ativar')
                                        <form method="POST"
                                              action="{{ route('admin.unidades-negocio.ativar', $unidade) }}"
                                              onsubmit="return confirm({{ json_encode('Ativar a Unidade de Negócio '.$unidade->nome.'?') }});"
                                              class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-soft-success" title="Ativar">
                                                <i class="ri-checkbox-circle-line"></i> Ativar
                                            </button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center text-muted py-4">
                            @if (($filtros['search'] ?? '') !== '' || ($filtros['status'] ?? null) !== null || ($filtros['possui_estoque'] ?? null) !== null || ($filtros['id_estado'] ?? null) !== null)
                                Nenhuma unidade corresponde aos filtros aplicados.
                            @else
                                Nenhuma unidade de negócio cadastrada.
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
        Exibindo <strong>{{ $exibindo }}</strong> de <strong>{{ $total }}</strong> unidade(s).
        @if (($filtros['search'] ?? '') !== '')
            · Pesquisa: <code>{{ $filtros['search'] }}</code>
        @endif
        @if (($filtros['status'] ?? null) !== null)
            · Status: <code>{{ $filtros['status'] === '1' ? 'Ativas' : 'Inativas' }}</code>
        @endif
        @if (($filtros['id_estado'] ?? null) !== null)
            · Estado: <code>{{ optional($estados->firstWhere('id', (int) $filtros['id_estado']))->nome ?? $filtros['id_estado'] }}</code>
        @endif
    </div>
    <x-admin.table-pagination :paginator="$unidadesNegocio" />
</div>
