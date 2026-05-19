@php
    /** @var \App\Models\Estado $estado */
@endphp

<div class="card">
    <div class="card-header">
        <h4 class="header-title mb-0">{{ $cardTitle ?? 'Dados do estado' }}</h4>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="id_cigam" class="form-label">ID CIGAM <span class="text-danger">*</span></label>
                <input type="text"
                       id="id_cigam"
                       name="id_cigam"
                       value="{{ old('id_cigam', $estado->id_cigam) }}"
                       class="form-control @error('id_cigam') is-invalid @enderror"
                       maxlength="6"
                       inputmode="numeric"
                       required
                       autofocus
                       placeholder="000001">
                @error('id_cigam')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-5">
                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text"
                       id="nome"
                       name="nome"
                       value="{{ old('nome', $estado->nome) }}"
                       class="form-control @error('nome') is-invalid @enderror"
                       maxlength="255"
                       required
                       placeholder="Ex.: CEARA">
                @error('nome')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-2">
                <label for="abreviacao" class="form-label">Sigla (UF) <span class="text-danger">*</span></label>
                <input type="text"
                       id="abreviacao"
                       name="abreviacao"
                       value="{{ old('abreviacao', $estado->abreviacao) }}"
                       class="form-control @error('abreviacao') is-invalid @enderror"
                       maxlength="2"
                       required
                       placeholder="CE">
                @error('abreviacao')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-12">
                <label for="descricao" class="form-label">Descrição (regra ICMS)</label>
                <input type="text"
                       id="descricao"
                       name="descricao"
                       value="{{ old('descricao', $estado->descricao) }}"
                       class="form-control @error('descricao') is-invalid @enderror"
                       maxlength="255"
                       placeholder="Ex.: PAGA ICMS NA ENTRADA DO ESTADO">
                @error('descricao')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Opcional. Texto de apoio sobre como o ICMS se aplica neste estado.</small>
            </div>
        </div>
    </div>

    <div class="card-footer d-flex gap-2 justify-content-end">
        <a href="{{ route('admin.estados.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line me-1"></i> Voltar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="ri-save-line me-1"></i> {{ $submitLabel ?? 'Salvar' }}
        </button>
    </div>
</div>
