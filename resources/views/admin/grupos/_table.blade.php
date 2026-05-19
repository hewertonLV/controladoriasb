@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Grupo>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Grupo> $grupos */
    $linhas = $grupos;
@endphp

<div class="card-body">
    <table id="grupos-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Criado</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $grupo)
                    <tr>
                        <td><span class="fw-semibold">{{ $grupo->nome }}</span></td>
                        <td data-order="{{ $grupo->created_at?->timestamp ?? 0 }}">{{ optional($grupo->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 justify-content-end flex-nowrap">
                                @can('grupos.editar')
                                    <a href="{{ route('admin.grupos.edit', $grupo) }}"
                                       class="admin-datatable-action-link text-primary"
                                       title="Editar">
                                        <i class="ri-pencil-line"></i>
                                    </a>
                                @endcan

                                @can('grupos.historico')
                                    <a href="{{ route('admin.grupos.historico', $grupo) }}"
                                       class="admin-datatable-action-link text-info"
                                       title="Histórico">
                                        <i class="ri-history-line"></i>
                                    </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted py-4">Nenhum grupo cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
</div>
