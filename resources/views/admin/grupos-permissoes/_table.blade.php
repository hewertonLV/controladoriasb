@php
    /** @var \Illuminate\Support\Collection<int, \Spatie\Permission\Models\Role>|\Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles */
    $linhas = $roles;
@endphp

<div class="card-body">
    <table id="grupos-permissoes-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Guard</th>
                <th class="text-center">Perm.</th>
                <th>Criado</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $role)
                <tr>
                    <td>
                        <span class="fw-semibold">{{ $role->name }}</span>
                        @if ($role->name === $roleProgramador)
                            <span class="badge bg-danger-subtle text-danger ms-1">Acesso total</span>
                        @endif
                    </td>
                    <td>
                        <span class="badge bg-light text-dark">{{ $role->guard_name }}</span>
                    </td>
                    <td class="text-center" data-order="{{ $role->name === $roleProgramador ? -1 : (int) $role->permissions_count }}">
                        @if ($role->name === $roleProgramador)
                            <span class="text-muted">—</span>
                        @else
                            <span class="badge bg-primary-subtle text-primary">{{ $role->permissions_count }}</span>
                        @endif
                    </td>
                    <td data-order="{{ $role->created_at?->timestamp ?? 0 }}">{{ optional($role->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="text-end">
                        @can('grupos-permissoes.editar')
                            <a href="{{ route('admin.grupos-permissoes.edit', $role) }}"
                               class="admin-datatable-action-link text-primary"
                               title="{{ $role->name === $roleProgramador ? 'Visualizar' : 'Editar' }}">
                                <i class="ri-{{ $role->name === $roleProgramador ? 'eye' : 'pencil' }}-line"></i>
                            </a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">Nenhum grupo cadastrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
