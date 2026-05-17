@php
    /** @var \App\Models\Cliente $cliente */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Praca> $pracas */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Grupo> $grupos */
@endphp

<div class="card">
    <div class="card-header">
        <h4 class="header-title mb-0">{{ $cardTitle ?? 'Dados do cliente' }}</h4>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="id_cigam" class="form-label">ID CIGAM <span class="text-danger">*</span></label>
                <input type="text"
                       id="id_cigam"
                       name="id_cigam"
                       value="{{ old('id_cigam', $cliente->id_cigam) }}"
                       class="form-control @error('id_cigam') is-invalid @enderror"
                       maxlength="6"
                       required
                       autofocus
                       placeholder="Ex.: 1 => 000001"
                       autocomplete="off">
                @error('id_cigam')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Armazenado somente com dígitos (6 caracteres, com zeros à esquerda).</small>
            </div>
            <div class="col-md-8">
                <label for="razao_social" class="form-label">Razão social <span class="text-danger">*</span></label>
                <input type="text"
                       id="razao_social"
                       name="razao_social"
                       value="{{ old('razao_social', $cliente->razao_social) }}"
                       class="form-control @error('razao_social') is-invalid @enderror"
                       maxlength="255"
                       required
                       placeholder="Razão social"
                       autocomplete="off">
                @error('razao_social')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-8">
                <label for="fantasia" class="form-label">Fantasia</label>
                <input type="text"
                       id="fantasia"
                       name="fantasia"
                       value="{{ old('fantasia', $cliente->fantasia) }}"
                       class="form-control @error('fantasia') is-invalid @enderror"
                       maxlength="255"
                       placeholder="Nome fantasia"
                       autocomplete="off">
                @error('fantasia')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4">
                <label for="cnpj_cpf" class="form-label">CPF/CNPJ <span class="text-danger">*</span></label>
                <input type="text"
                       id="cnpj_cpf"
                       name="cnpj_cpf"
                       value="{{ old('cnpj_cpf', $cliente->cnpj_cpf_formatado) }}"
                       class="form-control @error('cnpj_cpf') is-invalid @enderror"
                       maxlength="18"
                       required
                       placeholder="Apenas dígitos (ou com máscara)"
                       autocomplete="off">
                @error('cnpj_cpf')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4">
                <label for="id_unidade_negocio" class="form-label">Unidade de Negócio <span class="text-danger">*</span></label>
                <input type="number"
                       id="id_unidade_negocio"
                       name="id_unidade_negocio"
                       value="{{ old('id_unidade_negocio', $cliente->id_unidade_negocio) }}"
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
                <label for="id_praca" class="form-label">Praça <span class="text-danger">*</span></label>
                <select id="id_praca"
                        name="id_praca"
                        class="form-select @error('id_praca') is-invalid @enderror"
                        required>
                    <option value="">Selecione...</option>
                    @foreach ($pracas as $praca)
                        <option value="{{ $praca->id }}"
                                data-unidade="{{ $praca->id_unidade_negocio }}"
                                @selected((int) old('id_praca', $cliente->id_praca) === $praca->id)>
                            {{ $praca->nome }} (UN {{ $praca->id_unidade_negocio }})
                        </option>
                    @endforeach
                </select>
                @error('id_praca')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4">
                <label for="grupo_id" class="form-label">Grupo</label>
                <select id="grupo_id"
                        name="grupo_id"
                        class="form-select @error('grupo_id') is-invalid @enderror">
                    <option value="">Sem grupo</option>
                    @foreach ($grupos as $grupo)
                        <option value="{{ $grupo->id }}"
                                @selected((int) old('grupo_id', $cliente->grupo_id) === $grupo->id)>
                            {{ $grupo->nome }}
                        </option>
                    @endforeach
                </select>
                @error('grupo_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4">
                <label for="desconto_nf" class="form-label">Desconto NF <span class="text-danger">*</span></label>
                <input type="number"
                       id="desconto_nf"
                       name="desconto_nf"
                       value="{{ old('desconto_nf', $cliente->desconto_nf ?? '0.00') }}"
                       class="form-control @error('desconto_nf') is-invalid @enderror"
                       min="0"
                       step="0.01"
                       required>
                @error('desconto_nf')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4">
                <label for="desconto_contrato" class="form-label">Desconto Contrato <span class="text-danger">*</span></label>
                <input type="number"
                       id="desconto_contrato"
                       name="desconto_contrato"
                       value="{{ old('desconto_contrato', $cliente->desconto_contrato ?? '0.00') }}"
                       class="form-control @error('desconto_contrato') is-invalid @enderror"
                       min="0"
                       step="0.01"
                       required>
                @error('desconto_contrato')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    <div class="card-footer d-flex gap-2 justify-content-end">
        <a href="{{ route('admin.clientes.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line me-1"></i> Voltar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="ri-save-line me-1"></i> {{ $submitLabel ?? 'Salvar' }}
        </button>
    </div>
</div>
