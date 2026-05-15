@php
    /** @var \App\Models\Grupo $grupo */
@endphp

<div class="card">
    <div class="card-header">
        <h4 class="header-title mb-0">{{ $cardTitle ?? 'Dados do grupo' }}</h4>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-12">
                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text"
                       id="nome"
                       name="nome"
                       value="{{ old('nome', $grupo->nome) }}"
                       class="form-control @error('nome') is-invalid @enderror"
                       maxlength="255"
                       required
                       autofocus
                       placeholder="Nome do grupo">
                @error('nome')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Armazenado em maiúsculas; espaços nas extremidades são removidos ao salvar.</small>
            </div>
        </div>
    </div>

    <div class="card-footer d-flex gap-2 justify-content-end">
        <a href="{{ route('admin.grupos.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line me-1"></i> Voltar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="ri-save-line me-1"></i> {{ $submitLabel ?? 'Salvar' }}
        </button>
    </div>
</div>
