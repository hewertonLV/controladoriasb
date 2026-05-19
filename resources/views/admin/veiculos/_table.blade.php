@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Veiculo>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Veiculo> $veiculos */
    $linhas = $veiculos;
@endphp

<div class="card-body">
    <table id="veiculos-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
            <thead>
                <tr>
                    <th># SBS</th>
                    <th>Nome</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Criado</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $veiculo)
                    <tr class="{{ $veiculo->status === 'ATIVO' ? '' : 'text-muted bg-light bg-opacity-25' }}">
                        <td data-order="{{ (int) $veiculo->id_sbs }}">
                            <code class="small">{{ $veiculo->id_sbs }}</code>
                        </td>
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
                        <td data-order="{{ $veiculo->created_at?->timestamp ?? 0 }}">{{ optional($veiculo->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 justify-content-end flex-nowrap">
                                @can('veiculos.editar')
                                    <a href="{{ route('admin.veiculos.edit', $veiculo) }}"
                                       class="admin-datatable-action-link text-primary"
                                       title="Editar">
                                        <i class="ri-pencil-line"></i>
                                    </a>
                                @endcan

                                @can('veiculos.historico')
                                    <a href="{{ route('admin.veiculos.historico', $veiculo) }}"
                                       class="admin-datatable-action-link text-info"
                                       title="Histórico">
                                        <i class="ri-history-line"></i>
                                    </a>
                                @endcan

                                @if ($veiculo->status === 'ATIVO')
                                    @can('veiculos.inativar')
                                        <form method="POST"
                                              action="{{ route('admin.veiculos.inativar', $veiculo) }}"
                                              class="d-inline"
                                              data-confirm="Inativar o veículo {{ $veiculo->nome }}?"
                                              data-confirm-title="Inativar veículo"
                                              data-confirm-variant="danger"
                                              data-confirm-btn="Inativar">
                                            @csrf
                                            <button type="submit"
                                                    class="admin-datatable-action-link text-secondary border-0 bg-transparent p-0"
                                                    title="Inativar">
                                                <i class="ri-close-circle-line"></i>
                                            </button>
                                        </form>
                                    @endcan
                                @else
                                    @can('veiculos.reativar')
                                        <form method="POST"
                                              action="{{ route('admin.veiculos.reativar', $veiculo) }}"
                                              class="d-inline"
                                              data-confirm="Reativar o veículo {{ $veiculo->nome }}?"
                                              data-confirm-title="Reativar veículo"
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
                        <td colspan="6" class="text-center text-muted py-4">Nenhum veículo cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
</div>
