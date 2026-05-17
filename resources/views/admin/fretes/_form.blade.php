@php
    /** @var \App\Models\Frete $frete */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Veiculo> $veiculos */
    $criando = ! $frete->exists;
@endphp

<div class="card">
    <div class="card-header">
        <h4 class="header-title mb-0">{{ $cardTitle ?? 'Dados do frete' }}</h4>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text"
                       id="nome"
                       name="nome"
                       value="{{ old('nome', $frete->nome) }}"
                       class="form-control @error('nome') is-invalid @enderror"
                       maxlength="255"
                       required
                       autofocus
                       placeholder="Nome do frete">
                @error('nome')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-3">
                <label for="valor" class="form-label">Valor <span class="text-danger">*</span></label>
                <input type="number"
                       id="valor"
                       name="valor"
                       value="{{ old('valor', $frete->valor) }}"
                       class="form-control @error('valor') is-invalid @enderror"
                       min="0"
                       step="0.01"
                       required>
                @error('valor')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            @unless ($criando)
                <div class="col-md-3">
                    <label for="valor_fruta_kg" class="form-label">Valor fruta/kg <span class="text-danger">*</span></label>
                    <input type="number"
                           id="valor_fruta_kg"
                           name="valor_fruta_kg"
                           value="{{ old('valor_fruta_kg', $frete->valor_fruta_kg) }}"
                           class="form-control @error('valor_fruta_kg') is-invalid @enderror"
                           min="0"
                           step="0.01"
                           required>
                    @error('valor_fruta_kg')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            @endunless
            <div class="col-md-6">
                <label for="id_veiculo" class="form-label">Veículo <span class="text-danger">*</span></label>
                <select id="id_veiculo"
                        name="id_veiculo"
                        class="form-select @error('id_veiculo') is-invalid @enderror"
                        required>
                    <option value="">Selecione...</option>
                    @foreach ($veiculos as $veiculo)
                        <option value="{{ $veiculo->id }}"
                            @selected((int) old('id_veiculo', $frete->id_veiculo) === (int) $veiculo->id)>
                            {{ $veiculo->nome }} (ID SBS: {{ $veiculo->id_sbs }})
                        </option>
                    @endforeach
                </select>
                @error('id_veiculo')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Somente veículos ativos.</small>
            </div>
            @unless ($criando)
                <div class="col-md-3">
                    <label for="status_situacao" class="form-label">Situação <span class="text-danger">*</span></label>
                    <select id="status_situacao"
                            name="status_situacao"
                            class="form-select @error('status_situacao') is-invalid @enderror"
                            required>
                        <option value="ABERTA" @selected(old('status_situacao', $frete->status_situacao) === 'ABERTA')>Aberta</option>
                        <option value="ENCERRADA" @selected(old('status_situacao', $frete->status_situacao) === 'ENCERRADA')>Encerrada</option>
                    </select>
                    @error('status_situacao')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            @else
                <div class="col-md-6">
                    <div class="alert alert-info mb-0">
                        O frete será criado como <strong>Aberto</strong>. Enquanto não houver movimentações vinculadas, o valor fruta/kg inicial será igual ao valor total informado.
                    </div>
                </div>
            @endunless
            <div class="col-12">
                <label for="descricao" class="form-label">Descrição</label>
                <textarea id="descricao"
                          name="descricao"
                          rows="3"
                          class="form-control @error('descricao') is-invalid @enderror"
                          placeholder="Descrição opcional">{{ old('descricao', $frete->descricao) }}</textarea>
                @error('descricao')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    <div class="card-footer d-flex gap-2 justify-content-end">
        <a href="{{ route('admin.fretes.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line me-1"></i> Voltar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="ri-save-line me-1"></i> {{ $submitLabel ?? 'Salvar' }}
        </button>
    </div>
</div>
