@php
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $isPaginator = $fornecedores instanceof LengthAwarePaginator;
    $linhas = $isPaginator ? $fornecedores->items() : $fornecedores;
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
                    <x-admin.sortable-th label="Fantasia" sort="fantasia" :filtros="$filtros" />
                    <x-admin.sortable-th label="CPF/CNPJ" sort="cnpj_cpf" :filtros="$filtros" />
                    <x-admin.sortable-th label="Criado em" sort="created_at" :filtros="$filtros" />
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $fornecedor)
                    <tr>
                        <td><code>{{ $fornecedor->id_cigam }}</code></td>
                        <td>{{ $fornecedor->estado?->nome ?? '—' }}</td>
                        <td><span class="fw-semibold">{{ $fornecedor->razao_social }}</span></td>
                        <td>{{ $fornecedor->fantasia ?? '—' }}</td>
                        <td><code>{{ $fornecedor->cnpj_cpf_formatado }}</code></td>
                        <td>{{ optional($fornecedor->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                @can('fornecedores.visualizar')
                                    <a href="{{ route('admin.fornecedores.show', $fornecedor) }}"
                                       class="btn btn-sm btn-soft-secondary"
                                       title="Detalhes">
                                        <i class="ri-eye-line"></i> Detalhes
                                    </a>
                                @endcan
                                @can('fornecedores.editar')
                                    <a href="{{ route('admin.fornecedores.edit', $fornecedor) }}"
                                       class="btn btn-sm btn-soft-primary"
                                       title="Editar">
                                        <i class="ri-pencil-line"></i> Editar
                                    </a>
                                @endcan

                                @can('fornecedores.historico')
                                    <a href="{{ route('admin.fornecedores.historico', $fornecedor) }}"
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
                        <td colspan="7" class="text-center text-muted py-4">
                            @if (($filtros['search'] ?? '') !== '' || ($filtros['id_estado'] ?? null) !== null)
                                Nenhum fornecedor corresponde aos filtros aplicados.
                            @else
                                Nenhum fornecedor cadastrado.
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
        Exibindo <strong>{{ $exibindo }}</strong> de <strong>{{ $total }}</strong> fornecedor(es).
        @if (($filtros['search'] ?? '') !== '')
            · Pesquisa: <code>{{ $filtros['search'] }}</code>
        @endif
        @if (($filtros['id_estado'] ?? null) !== null)
            · Estado: <code>{{ optional($estados->firstWhere('id', (int) $filtros['id_estado']))->nome ?? $filtros['id_estado'] }}</code>
        @endif
    </div>
    <x-admin.table-pagination :paginator="$fornecedores" />
</div>
