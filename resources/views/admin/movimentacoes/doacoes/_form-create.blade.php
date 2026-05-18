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

    @php
        $itens = old('itens', [[
            'id_fruta' => old('id_fruta'),
            'qtd_fruta_um' => old('qtd_fruta_um'),
        ]]);
    @endphp

    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="mb-0">Itens</h5>
            <button type="button" class="btn btn-outline-primary btn-sm" data-add-item="doacao">
                <i class="ri-add-line me-1"></i> Adicionar fruta
            </button>
        </div>
        <div data-items-container="doacao">
            @foreach ($itens as $i => $item)
                <div class="row g-2 mb-2" data-item-row>
                    <div class="col-md-7">
                        <select name="itens[{{ $i }}][id_fruta]" class="form-select @error("itens.$i.id_fruta") is-invalid @enderror" required data-fruta-select>
                            <option value="">Fruta</option>
                            @foreach ($frutas as $f)
                                <option value="{{ $f->id }}" data-estoque-origens="{{ implode(',', $f->estoque_origem_empresa_ids ?? []) }}" @selected((string) ($item['id_fruta'] ?? '') === (string) $f->id)>{{ $f->nome }}</option>
                            @endforeach
                        </select>
                        @error("itens.$i.id_fruta")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="itens[{{ $i }}][qtd_fruta_um]" value="{{ $item['qtd_fruta_um'] ?? '' }}" class="form-control @error("itens.$i.qtd_fruta_um") is-invalid @enderror js-decimal-br" placeholder="Qtd UM" required>
                        @error("itens.$i.qtd_fruta_um")<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-outline-danger w-100" data-remove-item aria-label="Remover item">&times;</button>
                    </div>
                </div>
            @endforeach
        </div>
        <small class="text-muted">Cada fruta informada gera um lançamento de doação com os dados de cabeçalho acima.</small>
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

<template id="doacao-item-template">
    <div class="row g-2 mb-2" data-item-row>
        <div class="col-md-7">
            <select name="itens[__INDEX__][id_fruta]" class="form-select" required data-fruta-select>
                <option value="">Fruta</option>
                @foreach ($frutas as $f)
                    <option value="{{ $f->id }}" data-estoque-origens="{{ implode(',', $f->estoque_origem_empresa_ids ?? []) }}">{{ $f->nome }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <input type="text" name="itens[__INDEX__][qtd_fruta_um]" class="form-control js-decimal-br" placeholder="Qtd UM" required>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger w-100" data-remove-item aria-label="Remover item">&times;</button>
        </div>
    </div>
</template>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.querySelector('[data-items-container="doacao"]');
        const addButton = document.querySelector('[data-add-item="doacao"]');
        const template = document.getElementById('doacao-item-template');
        const origem = document.getElementById('id_empresa_origem');
        if (!container || !addButton || !template || !origem) return;

        const filtrarFrutasPorOrigem = () => {
            const origemId = origem.value;

            container.querySelectorAll('[data-fruta-select]').forEach((select) => {
                select.querySelectorAll('option').forEach((option) => {
                    if (!option.value) return;

                    const permitido = origemId !== '' && (option.dataset.estoqueOrigens || '').split(',').includes(origemId);
                    option.hidden = !permitido;
                    option.disabled = !permitido;
                });

                if (select.selectedOptions.length && select.selectedOptions[0].disabled) {
                    select.value = '';
                }
            });
        };

        const refreshRemoveButtons = () => {
            container.querySelectorAll('[data-remove-item]').forEach((button) => {
                button.disabled = container.querySelectorAll('[data-item-row]').length <= 1;
            });
        };

        addButton.addEventListener('click', () => {
            const index = container.querySelectorAll('[data-item-row]').length;
            container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
            filtrarFrutasPorOrigem();
            refreshRemoveButtons();
        });

        container.addEventListener('click', (event) => {
            if (!event.target.matches('[data-remove-item]')) return;
            event.target.closest('[data-item-row]')?.remove();
            refreshRemoveButtons();
        });

        origem.addEventListener('change', filtrarFrutasPorOrigem);
        filtrarFrutasPorOrigem();
        refreshRemoveButtons();
    });
</script>
