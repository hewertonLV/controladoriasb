@php
    /** @var \App\Models\Empresa $empresa */
    $statusValue = old('status', $empresa->exists ? (int) $empresa->status : 1);
    $tipoValue = old('tipo_pessoa', $empresa->tipo_pessoa ?? \App\Models\Empresa::TIPO_PESSOA_JURIDICA);
@endphp

<div class="card">
    <div class="card-header">
        <h4 class="header-title mb-0">Dados da empresa</h4>
        <p class="text-muted mb-0">CPF/CNPJ é armazenado apenas com dígitos. Use o tipo correto.</p>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label for="id_cigam" class="form-label">ID CIGAM <span class="text-danger">*</span></label>
                <input type="text" id="id_cigam" name="id_cigam"
                       value="{{ old('id_cigam', $empresa->id_cigam) }}"
                       class="form-control @error('id_cigam') is-invalid @enderror"
                       maxlength="50" required autofocus
                       placeholder="Código no ERP">
                @error('id_cigam')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="unidade_negocio" class="form-label">Unidade de Negócio <span class="text-danger">*</span></label>
                <input type="number" id="unidade_negocio" name="unidade_negocio"
                       value="{{ old('unidade_negocio', $empresa->unidade_negocio) }}"
                       class="form-control @error('unidade_negocio') is-invalid @enderror"
                       min="1" step="1" required
                       placeholder="Ex.: 1">
                @error('unidade_negocio')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="tipo_pessoa" class="form-label">Tipo de Pessoa <span class="text-danger">*</span></label>
                <select id="tipo_pessoa" name="tipo_pessoa"
                        class="form-select @error('tipo_pessoa') is-invalid @enderror" required>
                    <option value="{{ \App\Models\Empresa::TIPO_PESSOA_JURIDICA }}" @selected($tipoValue === \App\Models\Empresa::TIPO_PESSOA_JURIDICA)>Jurídica (CNPJ)</option>
                    <option value="{{ \App\Models\Empresa::TIPO_PESSOA_FISICA }}" @selected($tipoValue === \App\Models\Empresa::TIPO_PESSOA_FISICA)>Física (CPF)</option>
                </select>
                @error('tipo_pessoa')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-3">
                <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                <select id="status" name="status"
                        class="form-select @error('status') is-invalid @enderror" required>
                    <option value="1" @selected((int) $statusValue === 1)>Ativa</option>
                    <option value="0" @selected((int) $statusValue === 0)>Inativa</option>
                </select>
                @error('status')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-8">
                <label for="nome" class="form-label">Nome / Razão social <span class="text-danger">*</span></label>
                <input type="text" id="nome" name="nome"
                       value="{{ old('nome', $empresa->nome) }}"
                       class="form-control @error('nome') is-invalid @enderror"
                       maxlength="255" required
                       placeholder="Razão social completa">
                @error('nome')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-md-4">
                <label for="cpf_cnpj" class="form-label">CPF/CNPJ <span class="text-danger">*</span></label>
                <input type="text" id="cpf_cnpj" name="cpf_cnpj"
                       value="{{ old('cpf_cnpj', $empresa->cpf_cnpj) }}"
                       class="form-control @error('cpf_cnpj') is-invalid @enderror"
                       maxlength="18" required
                       placeholder="Apenas dígitos; máscara é ignorada">
                <small class="text-muted">Será salvo somente com dígitos.</small>
                @error('cpf_cnpj')
                    <div class="invalid-feedback d-block">{{ $message }}</div>
                @enderror
            </div>

            <div class="col-12">
                <label for="fantasia" class="form-label">Nome Fantasia</label>
                <input type="text" id="fantasia" name="fantasia"
                       value="{{ old('fantasia', $empresa->fantasia) }}"
                       class="form-control @error('fantasia') is-invalid @enderror"
                       maxlength="255"
                       placeholder="Opcional">
                @error('fantasia')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    <div class="card-footer d-flex gap-2 justify-content-end">
        <a href="{{ route('admin.empresas.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line me-1"></i> Voltar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="ri-save-line me-1"></i> {{ $submitLabel ?? 'Salvar' }}
        </button>
    </div>
</div>
