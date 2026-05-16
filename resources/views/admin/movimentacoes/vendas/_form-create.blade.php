@php
    $isEdit = isset($movimentacao);
    $opcoes = $opcoes ?? compact('empresas_origem', 'empresas_destino_cliente', 'unidades_faturamento', 'frutas', 'fretes');
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.movimentacoes.vendas.update', $movimentacao) : route('admin.movimentacoes.vendas.store') }}">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="row g-3">
        <div class="col-md-3">
            <label class="form-label">Número NF</label>
            <input name="numero_nf" class="form-control" required value="{{ old('numero_nf', $movimentacao->vendaNota->numero_nf ?? '') }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Origem física</label>
            <select name="id_empresa_origem" class="form-select" required>
                <option value="">Selecione</option>
                @foreach ($opcoes['empresas_origem'] as $empresa)
                    <option value="{{ $empresa->id }}" @selected((int) old('id_empresa_origem', $movimentacao->id_empresa_origem ?? 0) === $empresa->id)>
                        {{ $empresa->nomeExibicao() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Cliente destino</label>
            <select name="id_empresa_destino" class="form-select" required>
                <option value="">Selecione</option>
                @foreach ($opcoes['empresas_destino_cliente'] as $empresa)
                    <option value="{{ $empresa->id }}" @selected((int) old('id_empresa_destino', $movimentacao->id_empresa_destino ?? 0) === $empresa->id)>
                        {{ $empresa->nomeExibicao() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Unidade faturamento</label>
            <select name="id_unidade_negocio_faturamento" class="form-select" required>
                <option value="">Selecione</option>
                @foreach ($opcoes['unidades_faturamento'] as $unidade)
                    <option value="{{ $unidade->id }}" @selected((int) old('id_unidade_negocio_faturamento', $movimentacao->id_unidade_negocio_faturamento ?? 0) === $unidade->id)>
                        {{ $unidade->nome }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Data emissão</label>
            <input type="datetime-local" name="data_emissao" class="form-control" value="{{ old('data_emissao', isset($movimentacao) ? optional($movimentacao->data_movimentacao)->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}">
        </div>
        <div class="col-md-3">
            <label class="form-label">Frete</label>
            <select name="id_frete" class="form-select">
                <option value="">Sem frete</option>
                @foreach ($opcoes['fretes'] as $frete)
                    <option value="{{ $frete->id }}" @selected((int) old('id_frete', $movimentacao->id_frete ?? 0) === $frete->id)>
                        {{ $frete->nome ?? ('Frete #'.$frete->id) }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Observação</label>
            <input name="observacao" class="form-control" value="{{ old('observacao', $movimentacao->vendaNota->observacao ?? '') }}">
        </div>
    </div>

    <hr>

    @if ($isEdit)
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Fruta</label>
                <select name="id_fruta" class="form-select" required>
                    @foreach ($opcoes['frutas'] as $fruta)
                        <option value="{{ $fruta->id }}" @selected((int) old('id_fruta', $movimentacao->id_fruta) === $fruta->id)>{{ $fruta->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Quantidade UM</label>
                <input name="qtd_fruta_um" class="form-control" required value="{{ old('qtd_fruta_um', number_format((float) $movimentacao->qtd_fruta_um, 2, '.', '')) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Valor vendido</label>
                <input name="valor_nf_total" class="form-control money-mask" required value="{{ old('valor_nf_total', number_format((float) $movimentacao->valor_nf_total, 2, ',', '.')) }}">
            </div>
            <div class="col-md-12">
                <label class="form-label">Motivo da correção</label>
                <textarea name="motivo_substituicao" class="form-control" rows="2">{{ old('motivo_substituicao') }}</textarea>
            </div>
        </div>
    @else
        <h5 class="mb-3">Itens</h5>
        @for ($i = 0; $i < max(3, count(old('itens', []))); $i++)
            <div class="row g-3 mb-2">
                <div class="col-md-5">
                    <select name="itens[{{ $i }}][id_fruta]" class="form-select" @required($i === 0)>
                        <option value="">Fruta</option>
                        @foreach ($opcoes['frutas'] as $fruta)
                            <option value="{{ $fruta->id }}" @selected((int) old("itens.$i.id_fruta") === $fruta->id)>{{ $fruta->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <input name="itens[{{ $i }}][qtd_fruta_um]" class="form-control" placeholder="Qtd UM" value="{{ old("itens.$i.qtd_fruta_um") }}" @required($i === 0)>
                </div>
                <div class="col-md-4">
                    <input name="itens[{{ $i }}][valor_nf_total]" class="form-control money-mask" placeholder="Valor vendido" value="{{ old("itens.$i.valor_nf_total") }}" @required($i === 0)>
                </div>
            </div>
        @endfor
    @endif

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit">{{ $isEdit ? 'Salvar nova versão' : 'Registrar venda' }}</button>
        <a href="{{ route('admin.movimentacoes.vendas.index') }}" class="btn btn-light">Cancelar</a>
    </div>
</form>
