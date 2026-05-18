@php
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $isPaginator = $fornecedores instanceof LengthAwarePaginator;
    $linhas = $isPaginator ? $fornecedores->items() : $fornecedores;
    /** @var \Illuminate\Support\Collection<int, \App\Models\Estado>|null $estados */
    $estados = $estados ?? collect();
@endphp

<div class="card-body">
    <div class="table-responsive">
        <table id="fornecedores-datatable" class="table table-sm table-striped table-hover table-centered fornecedores-table mb-0 w-100">
            <thead>
                <tr>
                    <th># CI.</th>
                    <th>UF</th>
                    <th>Fornecedor</th>
                    <th>Doc.</th>
                    <th>Criado</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($linhas as $fornecedor)
                    <tr>
                        <td><code class="small">{{ $fornecedor->id_cigam }}</code></td>
                        <td>{{ $fornecedor->estado?->abreviacao ?? '—' }}</td>
                        <td><span class="fw-semibold">{{ $fornecedor->fantasia ?: $fornecedor->razao_social }}</span></td>
                        <td><code class="small">{{ $fornecedor->cnpj_cpf_formatado }}</code></td>
                        <td>{{ optional($fornecedor->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 justify-content-end">
                                @can('fornecedores.visualizar')
                                    <a href="{{ route('admin.fornecedores.show', $fornecedor) }}"
                                       class="fornecedor-action-link text-secondary"
                                       title="Detalhes">
                                        <i class="ri-eye-line"></i>
                                    </a>
                                @endcan
                                @can('fornecedores.editar')
                                    <a href="{{ route('admin.fornecedores.edit', $fornecedor) }}"
                                       class="fornecedor-action-link text-primary"
                                       title="Editar">
                                        <i class="ri-pencil-line"></i>
                                    </a>
                                @endcan

                                @can('fornecedores.historico')
                                    <a href="{{ route('admin.fornecedores.historico', $fornecedor) }}"
                                       class="fornecedor-action-link text-info"
                                       title="Histórico">
                                        <i class="ri-history-line"></i>
                                    </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
