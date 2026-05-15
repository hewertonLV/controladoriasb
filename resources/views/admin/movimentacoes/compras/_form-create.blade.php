@php
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Empresa> $empresas_origem */
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Empresa> $empresas_destino */
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Fruta> $frutas */
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Frete> $fretes */
@endphp

<form method="POST" action="{{ route('admin.movimentacoes.compras.store') }}" class="row g-3" id="form-compra-create">
    @csrf

    <div class="col-md-6">
        <label for="id_empresa_origem" class="form-label">Empresa fornecedora <span class="text-danger">*</span></label>
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
        <label for="id_empresa_destino" class="form-label">Unidade de negócio (destino) <span class="text-danger">*</span></label>
        <select name="id_empresa_destino" id="id_empresa_destino" class="form-select @error('id_empresa_destino') is-invalid @enderror" required>
            <option value="">Selecione…</option>
            @foreach ($empresas_destino as $empresa)
                <option value="{{ $empresa->id }}" @selected((string) old('id_empresa_destino') === (string) $empresa->id)>
                    {{ $empresa->nomeExibicao() }} (CIGAM {{ $empresa->idCigamExibicao() }})
                </option>
            @endforeach
        </select>
        @error('id_empresa_destino')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <small class="text-muted">Somente unidades com estoque ativo (controle de estoque).</small>
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
        <label for="id_frete" class="form-label">Frete <span class="text-danger">*</span></label>
        <select name="id_frete" id="id_frete" class="form-select @error('id_frete') is-invalid @enderror" required>
            <option value="">Selecione…</option>
            @foreach ($fretes as $frete)
                <option value="{{ $frete->id }}" @selected((string) old('id_frete') === (string) $frete->id)>
                    {{ $frete->nome }} — R$ {{ number_format((float) $frete->valor, 2, ',', '.') }}
                </option>
            @endforeach
        </select>
        @error('id_frete')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <small class="text-muted">Apenas fretes com situação ABERTA.</small>
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
        <small class="text-muted">Use vírgula para decimais (ex.: 10,5).</small>
    </div>

    <div class="col-md-6">
        <label for="valor_nf_total" class="form-label">Valor total da nota fiscal <span class="text-danger">*</span></label>
        <input type="text"
               name="valor_nf_total"
               id="valor_nf_total"
               data-mask-money-br
               value="{{ old('valor_nf_total') }}"
               class="form-control @error('valor_nf_total') is-invalid @enderror"
               inputmode="decimal"
               autocomplete="off"
               required>
        @error('valor_nf_total')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <small class="text-muted">Formato brasileiro (ex.: R$ 1.234,56 ou 1234,56).</small>
    </div>

    <div class="col-12 d-flex justify-content-end gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="ri-check-line me-1"></i> Salvar compra
        </button>
    </div>
</form>
