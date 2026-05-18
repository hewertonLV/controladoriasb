@php
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $isPaginator = $roles instanceof LengthAwarePaginator;
    $linhas = $isPaginator ? $roles->items() : $roles;
@endphp

<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-centered table-hover mb-0">
            <thead class="bg-light bg-opacity-50">
                <tr>
                    <x-admin.sortable-th label="Nome" sort="name" :filtros="$filtros" />
                    <x-admin.sortable-th label="Guard" sort="guard_name" :filtros="$filtros" />
                    <x-admin.sortable-th label="Perm." sort="permissions_count" :filtros="$filtros" class="text-center" />
                    <x-admin.sortable-th label="Criado" sort="created_at" :filtros="$filtros" />
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
                        <td class="text-center">
                            @if ($role->name === $roleProgramador)
                                <span class="text-muted">—</span>
                            @else
                                <span class="badge bg-primary-subtle text-primary">{{ $role->permissions_count }}</span>
                            @endif
                        </td>
                        <td>{{ optional($role->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            @can('grupos-permissoes.editar')
                                <a href="{{ route('admin.grupos-permissoes.edit', $role) }}"
                                   class="btn btn-sm btn-soft-primary"
                                   title="{{ $role->name === $roleProgramador ? 'Visualizar' : 'Editar' }}">
                                    <i class="ri-{{ $role->name === $roleProgramador ? 'eye' : 'pencil' }}-line"></i>
                                    {{ $role->name === $roleProgramador ? 'Visualizar' : 'Editar' }}
                                </a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            @if (($filtros['search'] ?? '') !== '')
                                Nenhum grupo corresponde à pesquisa.
                            @else
                                Nenhum grupo cadastrado.
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
        Exibindo <strong>{{ $exibindo }}</strong> de <strong>{{ $total }}</strong> grupo(s).
        @if (($filtros['search'] ?? '') !== '')
            · Pesquisa: <code>{{ $filtros['search'] }}</code>
        @endif
    </div>
    <x-admin.table-pagination :paginator="$roles" />
</div>
