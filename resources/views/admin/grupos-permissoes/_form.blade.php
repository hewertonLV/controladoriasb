@php
    /** @var \Spatie\Permission\Models\Role $role */
    /** @var array<string, array<int, array{id:int,name:string,action:string}>> $permissionGroups */
    /** @var \Illuminate\Support\Collection<int, int> $selectedPermissionIds */
    /** @var bool $isProgramador */
    $selectedIds = collect(old('permissions', $selectedPermissionIds->all() ?? []))
        ->map(fn ($id) => (int) $id)
        ->all();
    $isReadOnly = $isProgramador;
@endphp

<div class="card">
    <div class="card-header">
        <h4 class="header-title mb-0">{{ $cardTitle ?? 'Dados do grupo' }}</h4>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="name" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name"
                       value="{{ old('name', $role->name) }}"
                       class="form-control @error('name') is-invalid @enderror"
                       maxlength="255" required autofocus
                       @if ($isReadOnly) readonly @endif
                       placeholder="Ex.: Administrador, Controladoria...">
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label class="form-label">Guard</label>
                <input type="text" value="web" class="form-control" readonly disabled>
                <small class="text-muted">Guard padrão do sistema.</small>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <div class="me-auto">
            <h4 class="header-title mb-0">Permissões</h4>
            <p class="text-muted mb-0">Marque as permissões deste grupo.</p>
        </div>
        @unless ($isReadOnly)
            <div class="d-flex align-items-center gap-2">
                <div class="input-group input-group-sm" style="max-width: 240px;">
                    <span class="input-group-text"><i class="ri-search-line"></i></span>
                    <input type="search" id="permission-search" class="form-control"
                           placeholder="Filtrar permissão..." autocomplete="off">
                </div>
                <button type="button" class="btn btn-sm btn-light" data-permission-toggle="all">
                    Marcar todas
                </button>
                <button type="button" class="btn btn-sm btn-light" data-permission-toggle="none">
                    Limpar
                </button>
            </div>
        @endunless
    </div>

    <div class="card-body">
        @if ($isReadOnly)
            <p class="text-muted mb-0">
                Este grupo não usa permissões individuais. Programadores acessam todas as áreas automaticamente.
            </p>
        @else
            @error('permissions')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror
            @error('permissions.*')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror

            @if (count($permissionGroups) === 0)
                <p class="text-muted mb-0">Nenhuma permissão cadastrada no sistema.</p>
            @else
                <div class="row g-3" id="permission-groups">
                    @foreach ($permissionGroups as $groupName => $perms)
                        <div class="col-md-6 col-xl-4 permission-group" data-group-name="{{ Str::lower($groupName) }}">
                            <div class="border rounded p-3 h-100">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <h5 class="mb-0 fs-14 fw-semibold text-uppercase text-muted">{{ $groupName }}</h5>
                                    <div class="form-check form-check-sm m-0">
                                        <input class="form-check-input permission-group-toggle" type="checkbox"
                                               id="group-toggle-{{ Str::slug($groupName) }}"
                                               data-group="{{ Str::slug($groupName) }}">
                                        <label class="form-check-label small text-muted"
                                               for="group-toggle-{{ Str::slug($groupName) }}">todas</label>
                                    </div>
                                </div>
                                <div class="d-flex flex-column gap-1">
                                    @foreach ($perms as $perm)
                                        <div class="form-check permission-item"
                                             data-permission-name="{{ Str::lower($perm['name']) }}">
                                            <input class="form-check-input permission-checkbox"
                                                   type="checkbox"
                                                   name="permissions[]"
                                                   value="{{ $perm['id'] }}"
                                                   id="perm-{{ $perm['id'] }}"
                                                   data-group="{{ Str::slug($groupName) }}"
                                                   @checked(in_array($perm['id'], $selectedIds, true))>
                                            <label class="form-check-label" for="perm-{{ $perm['id'] }}">
                                                <span class="fw-medium">{{ $perm['action'] }}</span>
                                                <small class="text-muted d-block">{{ $perm['name'] }}</small>
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>

    <div class="card-footer d-flex gap-2 justify-content-end">
        <a href="{{ route('admin.grupos-permissoes.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line me-1"></i> Voltar
        </a>
        @unless ($isReadOnly)
            <button type="submit" class="btn btn-primary">
                <i class="ri-save-line me-1"></i> {{ $submitLabel ?? 'Salvar' }}
            </button>
        @endunless
    </div>
</div>

@unless ($isReadOnly)
    @push('scripts')
        <script>
            (function () {
                const search = document.getElementById('permission-search');
                const items = document.querySelectorAll('.permission-item');
                const groups = document.querySelectorAll('.permission-group');
                const groupToggles = document.querySelectorAll('.permission-group-toggle');
                const checkboxes = document.querySelectorAll('.permission-checkbox');

                function syncGroupToggle(slug) {
                    const groupCheckboxes = document.querySelectorAll(`.permission-checkbox[data-group="${slug}"]`);
                    const toggle = document.querySelector(`.permission-group-toggle[data-group="${slug}"]`);
                    if (!toggle || groupCheckboxes.length === 0) return;
                    const checked = Array.from(groupCheckboxes).filter(c => c.checked).length;
                    toggle.checked = checked === groupCheckboxes.length;
                    toggle.indeterminate = checked > 0 && checked < groupCheckboxes.length;
                }

                groupToggles.forEach(toggle => {
                    toggle.addEventListener('change', (e) => {
                        const slug = e.target.dataset.group;
                        document.querySelectorAll(`.permission-checkbox[data-group="${slug}"]`).forEach(cb => {
                            cb.checked = e.target.checked;
                        });
                    });
                });

                checkboxes.forEach(cb => {
                    cb.addEventListener('change', () => syncGroupToggle(cb.dataset.group));
                });

                groupToggles.forEach(t => syncGroupToggle(t.dataset.group));

                if (search) {
                    search.addEventListener('input', (e) => {
                        const term = e.target.value.trim().toLowerCase();
                        items.forEach(item => {
                            const name = item.dataset.permissionName || '';
                            item.style.display = (term === '' || name.includes(term)) ? '' : 'none';
                        });
                        groups.forEach(group => {
                            const visible = group.querySelectorAll('.permission-item:not([style*="display: none"])').length;
                            group.style.display = visible > 0 ? '' : 'none';
                        });
                    });
                }

                document.querySelectorAll('[data-permission-toggle]').forEach(btn => {
                    btn.addEventListener('click', () => {
                        const value = btn.dataset.permissionToggle === 'all';
                        checkboxes.forEach(cb => {
                            const item = cb.closest('.permission-item');
                            if (!item || item.style.display === 'none') return;
                            cb.checked = value;
                        });
                        groupToggles.forEach(t => syncGroupToggle(t.dataset.group));
                    });
                });
            })();
        </script>
    @endpush
@endunless
