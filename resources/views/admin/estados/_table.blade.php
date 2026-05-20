@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Estado>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Estado> $estados */
    $linhas = $estados;
@endphp

<div class="card-body">
    <table id="estados-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
        <thead>
            <tr>
                <th>ID CIGAM</th>
                <th>Nome</th>
                <th>Sigla</th>
                <th>Descrição</th>
                <th>Status</th>
                <th>Criado</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $estado)
                <tr class="{{ $estado->trashed() ? 'text-muted' : '' }}">
                    <td data-order="{{ (int) $estado->id_cigam }}"><code class="small">{{ $estado->id_cigam }}</code></td>
                    <td><span class="fw-semibold">{{ $estado->nome }}</span></td>
                    <td><code>{{ $estado->abreviacao }}</code></td>
                    <td>{{ $estado->descricao ?: '—' }}</td>
                    <td data-order="{{ $estado->trashed() ? 0 : 1 }}">
                        @if ($estado->trashed())
                            <span class="badge bg-secondary-subtle text-secondary">Inativo</span>
                        @else
                            <span class="badge bg-success-subtle text-success">Ativo</span>
                        @endif
                    </td>
                    <td data-order="{{ $estado->created_at?->timestamp ?? 0 }}">{{ optional($estado->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1 justify-content-end flex-nowrap">
                            @can('estados.editar')
                                <a href="{{ route('admin.estados.edit', $estado) }}"
                                   class="admin-datatable-action-link text-primary"
                                   title="Editar">
                                    <i class="ri-pencil-line"></i>
                                </a>
                            @endcan

                            @if (! $estado->trashed())
                                @can('estados.inativar')
                                    @if (($estado->unidades_negocio_count ?? 0) + ($estado->fornecedores_count ?? 0) + ($estado->frutas_icms_count ?? 0) === 0)
                                        <form method="POST"
                                              action="{{ route('admin.estados.inativar', $estado) }}"
                                              class="d-inline"
                                              data-confirm="Inativar o estado {{ $estado->nome }} ({{ $estado->abreviacao }})?"
                                              data-confirm-title="Inativar estado"
                                              data-confirm-variant="danger"
                                              data-confirm-btn="Inativar">
                                            @csrf
                                            <button type="submit"
                                                    class="admin-datatable-action-link text-secondary border-0 bg-transparent p-0"
                                                    title="Inativar">
                                                <i class="ri-close-circle-line"></i>
                                            </button>
                                        </form>
                                    @endif
                                @endcan
                            @else
                                @can('estados.reativar')
                                    <form method="POST"
                                          action="{{ route('admin.estados.reativar', $estado) }}"
                                          class="d-inline"
                                          data-confirm="Reativar o estado {{ $estado->nome }} ({{ $estado->abreviacao }})?"
                                          data-confirm-title="Reativar estado"
                                          data-confirm-variant="success"
                                          data-confirm-btn="Reativar">
                                        @csrf
                                        <button type="submit"
                                                class="admin-datatable-action-link text-success border-0 bg-transparent p-0"
                                                title="Reativar">
                                            <i class="ri-checkbox-circle-line"></i>
                                        </button>
                                    </form>
                                @endcan
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">Nenhum estado cadastrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
