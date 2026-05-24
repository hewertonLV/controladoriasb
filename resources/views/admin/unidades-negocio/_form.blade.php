@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Estado>|\App\Models\Estado[] $estados */
    /** @var \App\Models\UnidadeNegocio $unidadeNegocio */
@endphp

<div class="card">
    <div class="card-header">
        <h4 class="header-title mb-0">{{ $cardTitle ?? 'Dados da unidade de negócio' }}</h4>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="id_cigam" class="form-label">ID CIGAM <span class="text-danger">*</span></label>
                <input type="text"
                       id="id_cigam"
                       name="id_cigam"
                       value="{{ old('id_cigam', $unidadeNegocio->id_cigam) }}"
                       class="form-control @error('id_cigam') is-invalid @enderror"
                       maxlength="32"
                       required
                       autofocus
                       placeholder="Ex.: 1, 25, 123456..."
                       autocomplete="off">
                @error('id_cigam')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Código no ERP CIGAM (até 6 dígitos; zeros à esquerda ao salvar).</small>
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
                            @selected((string) old('id_estado', $unidadeNegocio->id_estado ?? '') === (string) $estado->id)>
                            {{ $estado->nome }}
                        </option>
                    @endforeach
                </select>
                @error('id_estado')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Define a regra de ICMS aplicável à unidade (cadastro de estados).</small>
            </div>
            <div class="col-md-8">
                <label for="razao_social" class="form-label">Razão social <span class="text-danger">*</span></label>
                <input type="text"
                       id="razao_social"
                       name="razao_social"
                       value="{{ old('razao_social', $unidadeNegocio->razao_social) }}"
                       class="form-control @error('razao_social') is-invalid @enderror"
                       maxlength="255"
                       required
                       placeholder="Razão social">
                @error('razao_social')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text"
                       id="nome"
                       name="nome"
                       value="{{ old('nome', $unidadeNegocio->nome) }}"
                       class="form-control @error('nome') is-invalid @enderror"
                       maxlength="255"
                       required
                       placeholder="Nome da unidade de negócio">
                @error('nome')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-6">
                <label for="cpf_cnpj" class="form-label">CPF/CNPJ</label>
                <input type="text"
                       id="cpf_cnpj"
                       name="cpf_cnpj"
                       value="{{ old('cpf_cnpj', $unidadeNegocio->cpf_cnpj_formatado) }}"
                       class="form-control @error('cpf_cnpj') is-invalid @enderror"
                       maxlength="18"
                       placeholder="Somente números ou com máscara">
                @error('cpf_cnpj')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Opcional. Quando informado, deve ter 11 dígitos (CPF) ou 14 dígitos (CNPJ).</small>
            </div>
            <div class="col-md-4">
                <label for="custo_operacional" class="form-label">Custo operacional <span class="text-danger">*</span></label>
                <input type="number"
                       id="custo_operacional"
                       name="custo_operacional"
                       value="{{ old('custo_operacional', $unidadeNegocio->custo_operacional ?? '0.00') }}"
                       class="form-control @error('custo_operacional') is-invalid @enderror"
                       min="0"
                       step="0.01"
                       required>
                @error('custo_operacional')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Valor decimal (mínimo 0,00). Alterações geram histórico automático.</small>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check form-switch pb-2">
                    <input type="hidden" name="possui_estoque" value="0">
                    <input type="checkbox"
                           class="form-check-input @error('possui_estoque') is-invalid @enderror"
                           id="possui_estoque"
                           name="possui_estoque"
                           value="1"
                           @checked(old('possui_estoque', $unidadeNegocio->possui_estoque ?? false))>
                    <label class="form-check-label" for="possui_estoque">Unidade controla estoque de frutas</label>
                    @error('possui_estoque')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check form-switch pb-2">
                    <input type="hidden" name="is_unidade_producao" value="0">
                    <input type="checkbox"
                           class="form-check-input @error('is_unidade_producao') is-invalid @enderror"
                           id="is_unidade_producao"
                           name="is_unidade_producao"
                           value="1"
                           @checked(old('is_unidade_producao', $unidadeNegocio->is_unidade_producao ?? false))>
                    <label class="form-check-label" for="is_unidade_producao">Unidade de produção (fazenda)</label>
                    @error('is_unidade_producao')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check form-switch pb-2">
                    <input type="hidden" name="is_hub" value="0">
                    <input type="checkbox"
                           class="form-check-input @error('is_hub') is-invalid @enderror"
                           id="is_hub"
                           name="is_hub"
                           value="1"
                           @checked(old('is_hub', $unidadeNegocio->is_hub ?? false))>
                    <label class="form-check-label" for="is_hub">Unidade HUB</label>
                    @error('is_hub')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check form-switch pb-2">
                    <input type="hidden" name="is_galpao_operacional" value="0">
                    <input type="checkbox"
                           class="form-check-input @error('is_galpao_operacional') is-invalid @enderror"
                           id="is_galpao_operacional"
                           name="is_galpao_operacional"
                           value="1"
                           @checked(old('is_galpao_operacional', $unidadeNegocio->is_galpao_operacional ?? false))>
                    <label class="form-check-label" for="is_galpao_operacional">Galpão operacional</label>
                    @error('is_galpao_operacional')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <small class="text-muted d-block">Centro de resultado regional (estoque gerencial, captação). Ex.: Recife, Maceió, CD Barbalha.</small>
                </div>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check form-switch pb-2">
                    <input type="hidden" name="emite_nota_fiscal" value="0">
                    <input type="checkbox"
                           class="form-check-input @error('emite_nota_fiscal') is-invalid @enderror"
                           id="emite_nota_fiscal"
                           name="emite_nota_fiscal"
                           value="1"
                           @checked(old('emite_nota_fiscal', $unidadeNegocio->emite_nota_fiscal ?? true))>
                    <label class="form-check-label" for="emite_nota_fiscal">Emite nota fiscal (CNPJ)</label>
                    @error('emite_nota_fiscal')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                    <small class="text-muted d-block">Origem comercial / faturamento na captação. Galpões regionais (Recife) costumam desmarcar; CD Barbalha marca os dois.</small>
                </div>
            </div>
        </div>
    </div>

    <div class="card-footer d-flex gap-2 justify-content-end">
        <a href="{{ route('admin.unidades-negocio.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line me-1"></i> Voltar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="ri-save-line me-1"></i> {{ $submitLabel ?? 'Salvar' }}
        </button>
    </div>
</div>
