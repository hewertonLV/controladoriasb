@php
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $isPaginator = $users instanceof LengthAwarePaginator;
    $linhas = $isPaginator ? $users->items() : $users;
@endphp

<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-centered table-hover mb-0">
            <thead class="bg-light bg-opacity-50">
                <tr>
                    <x-admin.sortable-th label="Nome" sort="name" :filtros="$filtros" />
                    <x-admin.sortable-th label="Login" sort="login" :filtros="$filtros" />
                    <x-admin.sortable-th label="Email" sort="email" :filtros="$filtros" />
                    <th>Grupos</th>
                    <x-admin.sortable-th label="Status" sort="ativo" :filtros="$filtros" />
                    <x-admin.sortable-th label="Senha" sort="must_change_password" :filtros="$filtros" />
                    <x-admin.sortable-th label="Criado" sort="created_at" :filtros="$filtros" />
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $user)
                    @php
                        $isProtected = strtolower((string) $user->email) === $protectedEmail;
                        $isProgramador = $user->hasRole(\App\Enums\Roles::PROGRAMADOR->value);
                        $isSelf = auth()->id() === $user->id;
                        $isInactive = ! $user->ativo;
                    @endphp
                    <tr class="{{ $isInactive ? 'text-muted bg-light bg-opacity-25' : '' }}">
                        <td>
                            <span class="fw-semibold">{{ $user->name }}</span>
                            @if ($isProtected)
                                <span class="badge bg-danger-subtle text-danger ms-1">Programador</span>
                            @endif
                        </td>
                        <td><code>{{ $user->login ?? '—' }}</code></td>
                        <td>{{ $user->email }}</td>
                        <td>
                            @forelse ($user->roles as $role)
                                <span class="badge bg-primary-subtle text-primary">{{ $role->name }}</span>
                            @empty
                                <span class="text-muted">—</span>
                            @endforelse
                        </td>
                        <td>
                            @if ($user->ativo)
                                <span class="badge bg-success-subtle text-success">
                                    <i class="ri-checkbox-circle-line me-1"></i>Ativo
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">
                                    <i class="ri-close-circle-line me-1"></i>Inativo
                                </span>
                            @endif
                        </td>
                        <td>
                            @if ($user->must_change_password)
                                <span class="badge bg-warning-subtle text-warning">
                                    <i class="ri-key-2-line me-1"></i>Senha temporária
                                </span>
                            @else
                                <span class="badge bg-info-subtle text-info">
                                    <i class="ri-shield-check-line me-1"></i>Senha definida
                                </span>
                            @endif
                        </td>
                        <td>{{ optional($user->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                @can('usuarios.editar')
                                    <a href="{{ route('admin.usuarios.edit', $user) }}"
                                       class="btn btn-sm btn-soft-primary"
                                       title="Editar">
                                        <i class="ri-pencil-line"></i> Editar
                                    </a>
                                @endcan

                                @can('usuarios.resetar-senha')
                                    @if (! $isProtected && ! $isSelf)
                                        <form method="POST"
                                              action="{{ route('admin.usuarios.reset-password', $user) }}"
                                              onsubmit="return confirm('Resetar a senha de {{ $user->name }} para a senha padrão?');"
                                              class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-soft-warning" title="Resetar senha">
                                                <i class="ri-refresh-line"></i> Resetar senha
                                            </button>
                                        </form>
                                    @endif
                                @endcan

                                @if ($user->ativo)
                                    @can('usuarios.desativar')
                                        @if (! $isProtected && ! $isProgramador && ! $isSelf)
                                            <form method="POST"
                                                  action="{{ route('admin.usuarios.desativar', $user) }}"
                                                  onsubmit="return confirm('Desativar o usuário {{ $user->name }}? Ele perderá o acesso ao sistema.');"
                                                  class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-soft-secondary" title="Desativar">
                                                    <i class="ri-user-unfollow-line"></i> Desativar
                                                </button>
                                            </form>
                                        @endif
                                    @endcan
                                @else
                                    @can('usuarios.reativar')
                                        <form method="POST"
                                              action="{{ route('admin.usuarios.reativar', $user) }}"
                                              onsubmit="return confirm('Reativar o usuário {{ $user->name }}?');"
                                              class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-soft-success" title="Reativar">
                                                <i class="ri-user-follow-line"></i> Reativar
                                            </button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            @if (($filtros['search'] ?? '') !== '')
                                Nenhum usuário corresponde à pesquisa.
                            @else
                                Nenhum usuário cadastrado.
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
        Exibindo <strong>{{ $exibindo }}</strong> de <strong>{{ $total }}</strong> usuário(s).
        @if (($filtros['search'] ?? '') !== '')
            · Pesquisa: <code>{{ $filtros['search'] }}</code>
        @endif
    </div>
    <x-admin.table-pagination :paginator="$users" />
</div>
