@php
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $isPaginator = $clientes instanceof LengthAwarePaginator;
    $linhas = $isPaginator ? $clientes->items() : $clientes;
@endphp

<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-centered table-hover mb-0">
            <thead class="bg-light bg-opacity-50">
                <tr>
                    <x-admin.sortable-th label="ID CIGAM" sort="id_cigam" :filtros="$filtros" />
                    <x-admin.sortable-th label="Razão social" sort="razao_social" :filtros="$filtros" />
                    <x-admin.sortable-th label="Fantasia" sort="fantasia" :filtros="$filtros" />
                    <x-admin.sortable-th label="CPF/CNPJ" sort="cnpj_cpf" :filtros="$filtros" />
                    <th>Praça</th>
                    <th>Grupo</th>
                    <x-admin.sortable-th label="Desconto NF" sort="desconto_nf" :filtros="$filtros" />
                    <x-admin.sortable-th label="Criado em" sort="created_at" :filtros="$filtros" />
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $cliente)
                    <tr>
                        <td><code>{{ $cliente->id_cigam }}</code></td>
                        <td><span class="fw-semibold">{{ $cliente->razao_social }}</span></td>
                        <td>{{ $cliente->fantasia ?? '—' }}</td>
                        <td><code>{{ $cliente->cnpj_cpf_formatado }}</code></td>
                        <td>{{ $cliente->praca?->nome ?? '—' }}</td>
                        <td>{{ $cliente->grupo?->nome ?? '—' }}</td>
                        <td>{{ number_format((float) $cliente->desconto_nf, 2, ',', '.') }}</td>
                        <td>{{ optional($cliente->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                @can('clientes.editar')
                                    <a href="{{ route('admin.clientes.edit', $cliente) }}"
                                       class="btn btn-sm btn-soft-primary"
                                       title="Editar">
                                        <i class="ri-pencil-line"></i> Editar
                                    </a>
                                @endcan

                                @can('clientes.historico')
                                    <a href="{{ route('admin.clientes.historico', $cliente) }}"
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
                        <td colspan="9" class="text-center text-muted py-4">
                            @if (($filtros['search'] ?? '') !== '')
                                Nenhum cliente corresponde aos filtros aplicados.
                            @else
                                Nenhum cliente cadastrado.
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
        Exibindo <strong>{{ $exibindo }}</strong> de <strong>{{ $total }}</strong> cliente(s).
        @if (($filtros['search'] ?? '') !== '')
            · Pesquisa: <code>{{ $filtros['search'] }}</code>
        @endif
    </div>
    <x-admin.table-pagination :paginator="$clientes" />
</div>

