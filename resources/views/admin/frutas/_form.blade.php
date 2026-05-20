@php
    use App\Enums\FrutaProcedencia;
    use App\Enums\FrutaUnidadeMedicao;
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
                <label for="procedencia" class="form-label">Procedência <span class="text-danger">*</span></label>
                <select id="procedencia"
                        name="procedencia"
                        class="form-select @error('procedencia') is-invalid @enderror"
                        required>
                    @foreach (FrutaProcedencia::cases() as $proc)
                        <option value="{{ $proc->value }}"
                            @selected(old('procedencia', $fruta->procedencia ?? FrutaProcedencia::NACIONAL->value) === $proc->value)>
                            {{ $proc->value }}
                        </option>
                    @endforeach
                </select>
                @error('procedencia')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Define qual alíquota de venda (nacional ou internacional) será usada no cálculo.</small>
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
        </div>
    </div>
</div>

@include('admin.frutas._icms_estados', [
    'estados' => $estados,
    'icmsForm' => $icmsForm,
])

<div class="d-flex gap-2 justify-content-end mb-3">
    <a href="{{ route('admin.frutas.index') }}" class="btn btn-light">
        <i class="ri-arrow-left-line me-1"></i> Voltar
    </a>
    <button type="submit" class="btn btn-primary">
        <i class="ri-save-line me-1"></i> {{ $submitLabel ?? 'Salvar' }}
    </button>
</div>
