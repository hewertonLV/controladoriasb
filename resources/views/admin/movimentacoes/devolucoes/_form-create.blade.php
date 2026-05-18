@php
    $isEdit = isset($movimentacao);
    $opcoes = $opcoes ?? compact('vendas', 'tipos', 'unidades_retorno');
@endphp

<form method="POST" action="{{ $isEdit ? route('admin.movimentacoes.devolucoes.update', $movimentacao) : route('admin.movimentacoes.devolucoes.store') }}">
    @csrf
    @if ($isEdit)
        @method('PUT')
    @endif

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Venda original</label>
            <select name="movimentacao_venda_origem_id" class="form-select" required>
                <option value="">Selecione</option>
                @foreach ($opcoes['vendas'] as $venda)
                    <option value="{{ $venda->id }}" @selected((int) old('movimentacao_venda_origem_id', $movimentacao->movimentacao_venda_origem_id ?? 0) === $venda->id)>
                        #{{ $venda->id }} — NF {{ $venda->vendaNota?->numero_nf ?? '—' }} — {{ $venda->empresaDestino?->nomeExibicao() ?? 'Cliente' }} — {{ $venda->fruta?->nome }}
                    </option>
                @endforeach
            </select>
            <small class="text-muted">A devolução usa custo, preço médio e faturamento congelados da venda selecionada.</small>
        </div>
        <div class="col-md-3">
            <label class="form-label">Tipo</label>
            <select name="tipo_devolucao" class="form-select" required>
                <option value="">Selecione</option>
                @foreach ($opcoes['tipos'] as $tipo)
                    <option value="{{ $tipo->value }}" @selected(old('tipo_devolucao', $movimentacao->tipo_devolucao ?? '') === $tipo->value)>
                        {{ $tipo->rotulo() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">NF devolução</label>
            <input name="numero_nf_devolucao" class="form-control" required value="{{ old('numero_nf_devolucao', $movimentacao->numero_nf_devolucao ?? '') }}">
        </div>
        <div class="col-md-4">
            <label class="form-label">Unidade física de retorno</label>
            <select name="id_unidade_negocio_retorno" class="form-select">
                <option value="">Obrigatória quando há retorno ao estoque</option>
                @foreach ($opcoes['unidades_retorno'] as $unidade)
                    <option value="{{ $unidade->id }}" @selected((int) old('id_unidade_negocio_retorno', $movimentacao->id_unidade_negocio_retorno ?? 0) === $unidade->id)>
                        {{ $unidade->nome }}
                    </option>
                @endforeach
            </select>
            <small class="text-muted">Usada como local físico real de entrada no estoque.</small>
        </div>
        <div class="col-md-3">
            <label class="form-label">Quantidade UM</label>
            <input name="qtd_fruta_um" class="form-control" required value="{{ old('qtd_fruta_um', isset($movimentacao) ? number_format((float) $movimentacao->qtd_fruta_um, 2, '.', '') : '') }}">
        </div>
        <div class="col-md-5">
            <label class="form-label">Motivo da devolução</label>
            <input name="motivo_devolucao" class="form-control" value="{{ old('motivo_devolucao', $movimentacao->motivo_devolucao ?? '') }}">
        </div>
        <div class="col-md-12">
            <label class="form-label">Observação</label>
            <textarea name="observacao" class="form-control" rows="2">{{ old('observacao', $movimentacao->observacao ?? '') }}</textarea>
        </div>
        @if ($isEdit)
            <div class="col-md-12">
                <label class="form-label">Motivo da correção</label>
                <textarea name="motivo_substituicao" class="form-control" rows="2">{{ old('motivo_substituicao') }}</textarea>
            </div>
        @endif
    </div>

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit">{{ $isEdit ? 'Salvar nova versão' : 'Registrar devolução' }}</button>
        <a href="{{ route('admin.movimentacoes.devolucoes.index') }}" class="btn btn-light">Cancelar</a>
    </div>
</form>
