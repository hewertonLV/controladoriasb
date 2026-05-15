@php
    /** @var \App\Models\Veiculo $veiculo */
@endphp

<div class="card">
    <div class="card-header">
        <h4 class="header-title mb-0">{{ $cardTitle ?? 'Dados do veículo' }}</h4>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="id_sbs" class="form-label">ID SBS <span class="text-danger">*</span></label>
                <input type="text"
                       id="id_sbs"
                       name="id_sbs"
                       value="{{ old('id_sbs', $veiculo->id_sbs) }}"
                       class="form-control @error('id_sbs') is-invalid @enderror"
                       maxlength="20"
                       required
                       autofocus
                       placeholder="Somente números"
                       autocomplete="off">
                @error('id_sbs')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-8">
                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text"
                       id="nome"
                       name="nome"
                       value="{{ old('nome', $veiculo->nome) }}"
                       class="form-control @error('nome') is-invalid @enderror"
                       maxlength="255"
                       required
                       placeholder="Nome do veículo"
                       autocomplete="off">
                @error('nome')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="tipo" class="form-label">Tipo <span class="text-danger">*</span></label>
                <input type="text"
                       id="tipo"
                       name="tipo"
                       value="{{ old('tipo', $veiculo->tipo) }}"
                       class="form-control @error('tipo') is-invalid @enderror"
                       maxlength="255"
                       required
                       placeholder="Ex.: CARRO, CAMINHÃO..."
                       autocomplete="off">
                @error('tipo')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="id_unidade_negocio" class="form-label">Unidade de Negócio <span class="text-danger">*</span></label>
                <input type="number"
                       id="id_unidade_negocio"
                       name="id_unidade_negocio"
                       value="{{ old('id_unidade_negocio', $veiculo->id_unidade_negocio) }}"
                       class="form-control @error('id_unidade_negocio') is-invalid @enderror"
                       min="1"
                       step="1"
                       required
                       placeholder="Ex.: 1">
                @error('id_unidade_negocio')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4">
                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                <select id="status"
                        name="status"
                        class="form-select @error('status') is-invalid @enderror"
                        required>
                    @php
                        $statusValue = old('status', $veiculo->exists ? $veiculo->status : 'ATIVO');
                    @endphp
                    <option value="ATIVO" @selected($statusValue === 'ATIVO')>Ativo</option>
                    <option value="INATIVO" @selected($statusValue === 'INATIVO')>Inativo</option>
                </select>
                @error('status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    <div class="card-footer d-flex gap-2 justify-content-end">
        <a href="{{ route('admin.veiculos.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line me-1"></i> Voltar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="ri-save-line me-1"></i> {{ $submitLabel ?? 'Salvar' }}
        </button>
    </div>
</div>

