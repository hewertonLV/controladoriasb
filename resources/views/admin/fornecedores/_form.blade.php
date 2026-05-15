@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Estado>|\App\Models\Estado[] $estados */
    /** @var \App\Models\Fornecedor $fornecedor */
@endphp

<div class="card">
    <div class="card-header">
        <h4 class="header-title mb-0">{{ $cardTitle ?? 'Dados do fornecedor' }}</h4>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="id_cigam" class="form-label">ID CIGAM <span class="text-danger">*</span></label>
                <input type="text"
                       id="id_cigam"
                       name="id_cigam"
                       value="{{ old('id_cigam', $fornecedor->id_cigam) }}"
                       class="form-control @error('id_cigam') is-invalid @enderror"
                       maxlength="32"
                       required
                       autofocus
                       placeholder="Ex.: 1, 25, 123456..."
                       autocomplete="off">
                @error('id_cigam')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Código no ERP CIGAM (até 6 dígitos; zeros à esquerda são aplicados ao salvar).</small>
            </div>
            <div class="col-md-4">
                <label for="id_estado" class="form-label">Estado (ICMS) <span class="text-danger">*</span></label>
                <select id="id_estado"
                        name="id_estado"
                        class="form-select @error('id_estado') is-invalid @enderror"
                        required>
                    <option value="">Selecione...</option>
                    @foreach ($estados as $estado)
                        <option value="{{ $estado->id }}"
                            @selected((string) old('id_estado', $fornecedor->id_estado ?? '') === (string) $estado->id)>
                            {{ $estado->nome }}
                        </option>
                    @endforeach
                </select>
                @error('id_estado')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Vínculo para regras fiscais, ICMS e consistência tributária.</small>
            </div>
            <div class="col-md-8">
                <label for="razao_social" class="form-label">Razão social <span class="text-danger">*</span></label>
                <input type="text"
                       id="razao_social"
                       name="razao_social"
                       value="{{ old('razao_social', $fornecedor->razao_social) }}"
                       class="form-control @error('razao_social') is-invalid @enderror"
                       maxlength="255"
                       required
                       placeholder="Razão social">
                @error('razao_social')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="fantasia" class="form-label">Fantasia</label>
                <input type="text"
                       id="fantasia"
                       name="fantasia"
                       value="{{ old('fantasia', $fornecedor->fantasia) }}"
                       class="form-control @error('fantasia') is-invalid @enderror"
                       maxlength="255"
                       placeholder="Nome fantasia (opcional)">
                @error('fantasia')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="cnpj_cpf" class="form-label">CPF/CNPJ <span class="text-danger">*</span></label>
                <input type="text"
                       id="cnpj_cpf"
                       name="cnpj_cpf"
                       value="{{ old('cnpj_cpf', $fornecedor->cnpj_cpf_formatado) }}"
                       class="form-control @error('cnpj_cpf') is-invalid @enderror"
                       maxlength="18"
                       required
                       placeholder="Somente números ou com máscara">
                @error('cnpj_cpf')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">11 dígitos (CPF) ou 14 dígitos (CNPJ); caracteres não numéricos são removidos ao salvar.</small>
            </div>
        </div>
    </div>

    <div class="card-footer d-flex gap-2 justify-content-end">
        <a href="{{ route('admin.fornecedores.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line me-1"></i> Voltar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="ri-save-line me-1"></i> {{ $submitLabel ?? 'Salvar' }}
        </button>
    </div>
</div>
