@php
    use App\Enums\FrutaUnidadeMedicao;
    use App\Enums\FrutaUmIcms;
    /** @var \App\Models\Fruta $fruta */
@endphp

<div class="card">
    <div class="card-header">
        <h4 class="header-title mb-0">{{ $cardTitle ?? 'Dados da fruta' }}</h4>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="id_cigam" class="form-label">ID CIGAM <span class="text-danger">*</span></label>
                <input type="text"
                       id="id_cigam"
                       name="id_cigam"
                       value="{{ old('id_cigam', $fruta->id_cigam) }}"
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
            <div class="col-md-8">
                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text"
                       id="nome"
                       name="nome"
                       value="{{ old('nome', $fruta->nome) }}"
                       class="form-control @error('nome') is-invalid @enderror"
                       maxlength="255"
                       required
                       placeholder="Nome da fruta">
                @error('nome')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="unidade_medicao" class="form-label">Unidade de medição <span class="text-danger">*</span></label>
                <select id="unidade_medicao"
                        name="unidade_medicao"
                        class="form-select @error('unidade_medicao') is-invalid @enderror"
                        required>
                    <option value="">Selecione...</option>
                    @foreach (FrutaUnidadeMedicao::cases() as $unidade)
                        <option value="{{ $unidade->value }}"
                            @selected(old('unidade_medicao', $fruta->unidade_medicao) === $unidade->value)>
                            {{ $unidade->value }}
                        </option>
                    @endforeach
                </select>
                @error('unidade_medicao')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="kg_por_unidade_medicao" class="form-label">Kg por unidade de medição <span class="text-danger">*</span></label>
                <input type="number"
                       id="kg_por_unidade_medicao"
                       name="kg_por_unidade_medicao"
                       value="{{ old('kg_por_unidade_medicao', $fruta->kg_por_unidade_medicao ?? '0.00') }}"
                       class="form-control @error('kg_por_unidade_medicao') is-invalid @enderror"
                       min="0"
                       step="0.01"
                       required>
                @error('kg_por_unidade_medicao')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-3">
                <label for="icms_ex_compra" class="form-label">ICMS externo na compra <span class="text-danger">*</span></label>
                <input type="text"
                       inputmode="decimal"
                       id="icms_ex_compra"
                       name="icms_ex_compra"
                       value="{{ old('icms_ex_compra', $fruta->icms_ex_compra ?? '0.00') }}"
                       class="form-control @error('icms_ex_compra') is-invalid @enderror"
                       required>
                @error('icms_ex_compra')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-3">
                <label for="icms_na_compra" class="form-label">ICMS nacional na compra <span class="text-danger">*</span></label>
                <input type="text"
                       inputmode="decimal"
                       id="icms_na_compra"
                       name="icms_na_compra"
                       value="{{ old('icms_na_compra', $fruta->icms_na_compra ?? '0.00') }}"
                       class="form-control @error('icms_na_compra') is-invalid @enderror"
                       required>
                @error('icms_na_compra')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-3">
                <label for="um_icms" class="form-label">Unidade do ICMS <span class="text-danger">*</span></label>
                <select id="um_icms"
                        name="um_icms"
                        class="form-select @error('um_icms') is-invalid @enderror"
                        required>
                    <option value="">Selecione...</option>
                    @foreach (FrutaUmIcms::cases() as $um)
                        <option value="{{ $um->value }}"
                            @selected(old('um_icms', $fruta->um_icms ?? FrutaUmIcms::KG->value) === $um->value)>
                            {{ $um->value }}
                        </option>
                    @endforeach
                </select>
                @error('um_icms')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-3">
                <label for="icms_venda" class="form-label">ICMS venda (%) <span class="text-danger">*</span></label>
                <input type="text"
                       inputmode="decimal"
                       id="icms_venda"
                       name="icms_venda"
                       value="{{ old('icms_venda', $fruta->icms_venda ?? '0.00') }}"
                       class="form-control @error('icms_venda') is-invalid @enderror"
                       required>
                @error('icms_venda')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    <div class="card-footer d-flex gap-2 justify-content-end">
        <a href="{{ route('admin.frutas.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line me-1"></i> Voltar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="ri-save-line me-1"></i> {{ $submitLabel ?? 'Salvar' }}
        </button>
    </div>
</div>
