@php
    /** @var \App\Models\Praca $praca */
    /** @var \Illuminate\Support\Collection<int, \App\Models\UnidadeNegocio> $unidadesNegocio */
@endphp

<div class="card">
    <div class="card-header">
        <h4 class="header-title mb-0">{{ $cardTitle ?? 'Dados da praça' }}</h4>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-8">
                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text"
                       id="nome"
                       name="nome"
                       value="{{ old('nome', $praca->nome) }}"
                       class="form-control @error('nome') is-invalid @enderror"
                       maxlength="255"
                       required
                       autofocus
                       placeholder="Nome da praça"
                       autocomplete="off">
                @error('nome')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4">
                <label for="id_unidade_negocio" class="form-label">Unidade de Negócio <span class="text-danger">*</span></label>
                <select id="id_unidade_negocio"
                        name="id_unidade_negocio"
                        class="form-select @error('id_unidade_negocio') is-invalid @enderror"
                        required>
                    <option value="">Selecione...</option>
                    @foreach ($unidadesNegocio as $unidade)
                        <option value="{{ $unidade->id }}"
                            @selected((int) old('id_unidade_negocio', $praca->id_unidade_negocio) === (int) $unidade->id)>
                            {{ $unidade->nome }} ({{ $unidade->id_cigam }})
                        </option>
                    @endforeach
                </select>
                @error('id_unidade_negocio')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    <div class="card-footer d-flex gap-2 justify-content-end">
        <a href="{{ route('admin.pracas.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line me-1"></i> Voltar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="ri-save-line me-1"></i> {{ $submitLabel ?? 'Salvar' }}
        </button>
    </div>
</div>
