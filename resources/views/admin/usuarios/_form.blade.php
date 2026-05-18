@php
    /** @var \App\Models\User $user */
    /** @var \Illuminate\Support\Collection<int, \Spatie\Permission\Models\Role> $roles */
    /** @var \Illuminate\Support\Collection<int, int>|array<int,int> $selectedRoleIds */
    /** @var \Illuminate\Support\Collection<int, \App\Models\UnidadeNegocio> $unidadesNegocio */
    /** @var \Illuminate\Support\Collection<int, int>|array<int,int> $selectedUnidadeNegocioIds */
    /** @var bool $isProtected */
    $selectedIds = collect(old('roles', is_object($selectedRoleIds) ? $selectedRoleIds->all() : $selectedRoleIds))
        ->map(fn ($id) => (int) $id)
        ->all();
    $selectedUnidadesIds = collect(old('unidades_negocio', is_object($selectedUnidadeNegocioIds ?? null) ? $selectedUnidadeNegocioIds->all() : ($selectedUnidadeNegocioIds ?? [])))
        ->map(fn ($id) => (int) $id)
        ->all();
@endphp

<div class="card">
    <div class="card-header">
        <h4 class="header-title mb-0">{{ $cardTitle ?? 'Dados do usuário' }}</h4>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="name" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name"
                       value="{{ old('name', $user->name) }}"
                       class="form-control @error('name') is-invalid @enderror"
                       maxlength="255" required autofocus
                       placeholder="Nome completo">
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="login" class="form-label">Login <span class="text-danger">*</span></label>
                <input type="text" id="login" name="login"
                       value="{{ old('login', $user->login) }}"
                       class="form-control @error('login') is-invalid @enderror"
                       maxlength="60" required
                       placeholder="ex.: jose.silva">
                <small class="text-muted">Letras minúsculas, números, ponto, hífen ou underline.</small>
                @error('login')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="email" class="form-label">E-mail <span class="text-danger">*</span></label>
                <input type="email" id="email" name="email"
                       value="{{ old('email', $user->email) }}"
                       class="form-control @error('email') is-invalid @enderror"
                       maxlength="255" required
                       placeholder="usuario@dominio.com.br">
                @error('email')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h4 class="header-title mb-0">Grupos / Perfis</h4>
        <p class="text-muted mb-0">Vincule o usuário aos grupos de permissão correspondentes.</p>
    </div>
    <div class="card-body">
        @error('roles')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror
        @error('roles.*')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror

        @if ($roles->isEmpty())
            <p class="text-muted mb-0">Nenhum grupo cadastrado. Cadastre grupos antes de vincular usuários.</p>
        @else
            <div class="row g-2">
                @foreach ($roles as $role)
                    @php
                        $isProgramadorRole = $role->name === \App\Enums\Roles::PROGRAMADOR->value;
                        $forceChecked = $isProtected && $isProgramadorRole;
                        $checked = $forceChecked || in_array($role->id, $selectedIds, true);
                    @endphp
                    <div class="col-md-4 col-sm-6">
                        <div class="form-check border rounded p-2 ps-4 h-100">
                            <input class="form-check-input" type="checkbox"
                                   name="roles[]" value="{{ $role->id }}"
                                   id="role-{{ $role->id }}"
                                   @checked($checked)
                                   @disabled($forceChecked)>
                            <label class="form-check-label w-100" for="role-{{ $role->id }}">
                                <span class="fw-medium">{{ $role->name }}</span>
                                @if ($isProgramadorRole)
                                    <span class="badge bg-danger-subtle text-danger ms-1">Acesso total</span>
                                @endif
                            </label>
                            @if ($forceChecked)
                                <input type="hidden" name="roles[]" value="{{ $role->id }}">
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h4 class="header-title mb-0">Unidades de Negócio Permitidas</h4>
        <p class="text-muted mb-0">Selecione as unidades que este usuário pode movimentar. Programador e Administrador mantêm acesso total.</p>
    </div>
    <div class="card-body">
        @error('unidades_negocio')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror
        @error('unidades_negocio.*')
            <div class="alert alert-danger">{{ $message }}</div>
        @enderror

        @if (($unidadesNegocio ?? collect())->isEmpty())
            <p class="text-muted mb-0">Nenhuma unidade de negócio ativa cadastrada.</p>
        @else
            <div class="row g-2">
                @foreach ($unidadesNegocio as $unidade)
                    <div class="col-md-4 col-sm-6">
                        <div class="form-check border rounded p-2 ps-4 h-100">
                            <input class="form-check-input" type="checkbox"
                                   name="unidades_negocio[]" value="{{ $unidade->id }}"
                                   id="unidade-negocio-{{ $unidade->id }}"
                                   @checked(in_array($unidade->id, $selectedUnidadesIds, true))>
                            <label class="form-check-label w-100" for="unidade-negocio-{{ $unidade->id }}">
                                <span class="fw-medium">{{ $unidade->nome }}</span>
                                <span class="text-muted d-block small">CIGAM {{ $unidade->id_cigam ?: '—' }}</span>
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="card-footer d-flex gap-2 justify-content-end">
        <a href="{{ route('admin.usuarios.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line me-1"></i> Voltar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="ri-save-line me-1"></i> {{ $submitLabel ?? 'Salvar' }}
        </button>
    </div>
</div>
