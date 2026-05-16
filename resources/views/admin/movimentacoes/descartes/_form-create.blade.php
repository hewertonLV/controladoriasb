<form method="post" action="{{ route('admin.movimentacoes.descartes.store') }}" class="row g-3">
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
        <label for="categoria_descarte_id" class="form-label">Categoria de descarte <span class="text-danger">*</span></label>
        <select name="categoria_descarte_id" id="categoria_descarte_id" class="form-select @error('categoria_descarte_id') is-invalid @enderror" required>
            <option value="">Selecione…</option>
            @foreach ($categorias_descarte as $categoria)
                <option value="{{ $categoria->id }}" @selected(old('categoria_descarte_id') == $categoria->id)>{{ $categoria->nome }}</option>
            @endforeach
        </select>
        @error('categoria_descarte_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="data_movimentacao" class="form-label">Data da movimentação</label>
        <input type="datetime-local" name="data_movimentacao" id="data_movimentacao" value="{{ old('data_movimentacao') }}"
               class="form-control @error('data_movimentacao') is-invalid @enderror">
        @error('data_movimentacao')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="motivo_descarte" class="form-label">Motivo do descarte</label>
        <textarea name="motivo_descarte" id="motivo_descarte" rows="3" class="form-control @error('motivo_descarte') is-invalid @enderror">{{ old('motivo_descarte') }}</textarea>
        @error('motivo_descarte')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="observacao" class="form-label">Observação</label>
        <textarea name="observacao" id="observacao" rows="3" class="form-control @error('observacao') is-invalid @enderror">{{ old('observacao') }}</textarea>
        @error('observacao')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">Salvar descarte</button>
    </div>
</form>
