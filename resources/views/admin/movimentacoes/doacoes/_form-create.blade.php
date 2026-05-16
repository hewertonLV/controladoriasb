<form method="post" action="{{ route('admin.movimentacoes.doacoes.store') }}" class="row g-3">
    @csrf

    <div class="col-md-6">
        <label for="id_empresa_origem" class="form-label">Unidade de origem <span class="text-danger">*</span></label>
        <select name="id_empresa_origem" id="id_empresa_origem" class="form-select @error('id_empresa_origem') is-invalid @enderror" required>
            <option value="">Selecione…</option>
            @foreach ($empresas_origem as $e)
                <option value="{{ $e->id }}" @selected(old('id_empresa_origem') == $e->id)>{{ $e->nomeExibicao() }}</option>
            @endforeach
        </select>
        @error('id_empresa_origem')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="id_fruta" class="form-label">Fruta <span class="text-danger">*</span></label>
        <select name="id_fruta" id="id_fruta" class="form-select @error('id_fruta') is-invalid @enderror" required>
            <option value="">Selecione…</option>
            @foreach ($frutas as $f)
                <option value="{{ $f->id }}" @selected(old('id_fruta') == $f->id)>{{ $f->nome }}</option>
            @endforeach
        </select>
        @error('id_fruta')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="qtd_fruta_um" class="form-label">Quantidade (UM) <span class="text-danger">*</span></label>
        <input type="text" name="qtd_fruta_um" id="qtd_fruta_um" value="{{ old('qtd_fruta_um') }}"
               class="form-control @error('qtd_fruta_um') is-invalid @enderror js-decimal-br" required>
        @error('qtd_fruta_um')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-8">
        <label for="id_empresa_destino" class="form-label">Cliente destino (opcional)</label>
        <select name="id_empresa_destino" id="id_empresa_destino" class="form-select @error('id_empresa_destino') is-invalid @enderror">
            <option value="">—</option>
            @foreach ($empresas_destino_cliente as $e)
                <option value="{{ $e->id }}" @selected(old('id_empresa_destino') == $e->id)>{{ $e->nomeExibicao() }}</option>
            @endforeach
        </select>
        @error('id_empresa_destino')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="motivo_doacao" class="form-label">Motivo da doação <span class="text-danger">*</span></label>
        <input type="text" name="motivo_doacao" id="motivo_doacao" value="{{ old('motivo_doacao') }}" maxlength="255"
               class="form-control @error('motivo_doacao') is-invalid @enderror" required>
        @error('motivo_doacao')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="numero_nf_origem" class="form-label">Número NF origem</label>
        <input type="text" name="numero_nf_origem" id="numero_nf_origem" value="{{ old('numero_nf_origem') }}" maxlength="120"
               class="form-control @error('numero_nf_origem') is-invalid @enderror">
        @error('numero_nf_origem')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="data_movimentacao" class="form-label">Data da movimentação</label>
        <input type="datetime-local" name="data_movimentacao" id="data_movimentacao" value="{{ old('data_movimentacao') }}"
               class="form-control @error('data_movimentacao') is-invalid @enderror">
        @error('data_movimentacao')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="observacao" class="form-label">Observação</label>
        <textarea name="observacao" id="observacao" rows="3" class="form-control @error('observacao') is-invalid @enderror">{{ old('observacao') }}</textarea>
        @error('observacao')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">Salvar doação</button>
    </div>
</form>
