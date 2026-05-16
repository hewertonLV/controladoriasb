@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Empresa> $empresas_origem */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Empresa> $empresas_destino */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Fruta> $frutas */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Frete> $fretes */
@endphp

<form method="POST" action="{{ route('admin.movimentacoes.transferencias.store') }}" class="row g-3" id="form-transferencia-create">
    @csrf

    <div class="col-md-6">
        <label for="id_empresa_origem" class="form-label">Unidade de origem <span class="text-danger">*</span></label>
        <select name="id_empresa_origem" id="id_empresa_origem" class="form-select @error('id_empresa_origem') is-invalid @enderror" required>
            <option value="">Selecione…</option>
            @foreach ($empresas_origem as $empresa)
                <option value="{{ $empresa->id }}" @selected((string) old('id_empresa_origem') === (string) $empresa->id)>
                    {{ $empresa->nomeExibicao() }} (CIGAM {{ $empresa->idCigamExibicao() }})
                </option>
            @endforeach
        </select>
        @error('id_empresa_origem')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="id_empresa_destino" class="form-label">Unidade de destino <span class="text-danger">*</span></label>
        <select name="id_empresa_destino" id="id_empresa_destino" class="form-select @error('id_empresa_destino') is-invalid @enderror" required>
            <option value="">Selecione…</option>
            @foreach ($empresas_destino as $empresa)
                <option value="{{ $empresa->id }}" @selected((string) old('id_empresa_destino') === (string) $empresa->id)>
                    {{ $empresa->nomeExibicao() }} (CIGAM {{ $empresa->idCigamExibicao() }})
                </option>
            @endforeach
        </select>
        @error('id_empresa_destino')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <small class="text-muted">Somente unidades com estoque ativo. O destino recebe entrada pendente até a conferência.</small>
    </div>

    <div class="col-md-6">
        <label for="id_fruta" class="form-label">Fruta <span class="text-danger">*</span></label>
        <select name="id_fruta" id="id_fruta" class="form-select @error('id_fruta') is-invalid @enderror" required>
            <option value="">Selecione…</option>
            @foreach ($frutas as $fruta)
                <option value="{{ $fruta->id }}" @selected((string) old('id_fruta') === (string) $fruta->id)>
                    {{ $fruta->nome }} ({{ $fruta->unidade_medicao }}) — {{ $fruta->kg_por_unidade_medicao }} kg/UM
                </option>
            @endforeach
        </select>
        @error('id_fruta')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="id_frete" class="form-label">Frete</label>
        <select name="id_frete" id="id_frete" class="form-select @error('id_frete') is-invalid @enderror">
            <option value="">Sem frete (valores zerados)</option>
            @foreach ($fretes as $frete)
                <option value="{{ $frete->id }}" @selected((string) old('id_frete') === (string) $frete->id)>
                    {{ $frete->nome }} — R$ {{ number_format((float) $frete->valor, 2, ',', '.') }}
                </option>
            @endforeach
        </select>
        @error('id_frete')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <small class="text-muted">Opcional. Apenas fretes ABERTOS.</small>
    </div>

    <div class="col-md-6">
        <label for="qtd_fruta_um" class="form-label">Quantidade na unidade de medida <span class="text-danger">*</span></label>
        <input type="text"
               name="qtd_fruta_um"
               id="qtd_fruta_um"
               data-mask-decimal-br
               value="{{ old('qtd_fruta_um') }}"
               class="form-control @error('qtd_fruta_um') is-invalid @enderror"
               inputmode="decimal"
               autocomplete="off"
               required>
        @error('qtd_fruta_um')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="numero_nf_origem" class="form-label">Número NF origem</label>
        <input type="text" name="numero_nf_origem" id="numero_nf_origem" value="{{ old('numero_nf_origem') }}"
               class="form-control @error('numero_nf_origem') is-invalid @enderror" maxlength="120">
        @error('numero_nf_origem')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label for="observacao" class="form-label">Observação</label>
        <textarea name="observacao" id="observacao" rows="2" class="form-control @error('observacao') is-invalid @enderror">{{ old('observacao') }}</textarea>
        @error('observacao')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">Salvar transferência</button>
    </div>
</form>
