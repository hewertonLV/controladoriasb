@php
    /** @var \App\Models\GrupoContrato $grupoContrato */
@endphp

<div class="card">
    <div class="card-header">
        <h4 class="header-title mb-0">{{ $cardTitle ?? 'Dados do grupo de contrato' }}</h4>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-8">
                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text"
                       id="nome"
                       name="nome"
                       value="{{ old('nome', $grupoContrato->nome) }}"
                       class="form-control @error('nome') is-invalid @enderror"
                       maxlength="255"
                       required
                       autofocus>
                @error('nome')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4">
                <label for="ativo" class="form-label">Status <span class="text-danger">*</span></label>
                <select id="ativo" name="ativo" class="form-select @error('ativo') is-invalid @enderror" required>
                    <option value="1" @selected((string) old('ativo', (int) $grupoContrato->ativo) === '1')>Ativo</option>
                    <option value="0" @selected((string) old('ativo', (int) $grupoContrato->ativo) === '0')>Inativo</option>
                </select>
                @error('ativo')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-12">
                <label for="descricao" class="form-label">Descrição</label>
                <textarea id="descricao"
                          name="descricao"
                          class="form-control @error('descricao') is-invalid @enderror"
                          rows="3">{{ old('descricao', $grupoContrato->descricao) }}</textarea>
                @error('descricao')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    <div class="card-footer d-flex gap-2 justify-content-end">
        <a href="{{ route('admin.grupos-contrato.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line me-1"></i> Voltar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="ri-save-line me-1"></i> {{ $submitLabel ?? 'Salvar' }}
        </button>
    </div>
</div>
