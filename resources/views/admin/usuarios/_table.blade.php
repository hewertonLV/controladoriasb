@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\User>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users */
    $linhas = $users;
@endphp

<div class="card-body">
    <table id="usuarios-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Login</th>
                <th>E-mail</th>
                <th>Grupos</th>
                <th>Status</th>
                <th>Senha</th>
                <th>Criado</th>
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
                <tr class="{{ $isInactive ? 'text-muted' : '' }}">
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
                    <td data-order="{{ $user->ativo ? 1 : 0 }}">
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
                    <td data-order="{{ $user->must_change_password ? 1 : 0 }}">
                        @if ($user->must_change_password)
                            <span class="badge bg-warning-subtle text-warning">
                                <i class="ri-key-2-line me-1"></i>Temporária
                            </span>
                        @else
                            <span class="badge bg-info-subtle text-info">
                                <i class="ri-shield-check-line me-1"></i>Definida
                            </span>
                        @endif
                    </td>
                    <td data-order="{{ $user->created_at?->timestamp ?? 0 }}">{{ optional($user->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1 justify-content-end flex-nowrap">
                            @can('usuarios.editar')
                                <a href="{{ route('admin.usuarios.edit', $user) }}"
                                   class="admin-datatable-action-link text-primary"
                                   title="Editar">
                                    <i class="ri-pencil-line"></i>
                                </a>
                            @endcan

                            @can('usuarios.resetar-senha')
                                @if (! $isProtected && ! $isSelf)
                                    <form method="POST"
                                          action="{{ route('admin.usuarios.reset-password', $user) }}"
                                          class="d-inline"
                                          data-confirm="Resetar a senha de {{ $user->name }} para a senha padrão?"
                                          data-confirm-title="Resetar senha"
                                          data-confirm-variant="warning"
                                          data-confirm-btn="Resetar senha">
                                        @csrf
                                        <button type="submit"
                                                class="admin-datatable-action-link text-warning border-0 bg-transparent p-0"
                                                title="Resetar senha">
                                            <i class="ri-refresh-line"></i>
                                        </button>
                                    </form>
                                @endif
                            @endcan

                            @if ($user->ativo)
                                @can('usuarios.desativar')
                                    @if (! $isProtected && ! $isProgramador && ! $isSelf)
                                        <form method="POST"
                                              action="{{ route('admin.usuarios.desativar', $user) }}"
                                              class="d-inline"
                                              data-confirm="Desativar o usuário {{ $user->name }}? Ele perderá o acesso ao sistema."
                                              data-confirm-title="Desativar usuário"
                                              data-confirm-variant="danger"
                                              data-confirm-btn="Desativar">
                                            @csrf
                                            <button type="submit"
                                                    class="admin-datatable-action-link text-secondary border-0 bg-transparent p-0"
                                                    title="Desativar">
                                                <i class="ri-user-unfollow-line"></i>
                                            </button>
                                        </form>
                                    @endif
                                @endcan
                            @else
                                @can('usuarios.reativar')
                                    <form method="POST"
                                          action="{{ route('admin.usuarios.reativar', $user) }}"
                                          class="d-inline"
                                          data-confirm="Reativar o usuário {{ $user->name }}?"
                                          data-confirm-title="Reativar usuário"
                                          data-confirm-variant="success"
                                          data-confirm-btn="Reativar">
                                        @csrf
                                        <button type="submit"
                                                class="admin-datatable-action-link text-success border-0 bg-transparent p-0"
                                                title="Reativar">
                                            <i class="ri-user-follow-line"></i>
                                        </button>
                                    </form>
                                @endcan
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">Nenhum usuário cadastrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
