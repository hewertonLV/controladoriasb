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

    @php
        $itens = old('itens', [[
            'id_fruta' => old('id_fruta'),
            'qtd_fruta_um' => old('qtd_fruta_um'),
        ]]);
    @endphp

    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="mb-0">Itens</h5>
            <button type="button" class="btn btn-outline-primary btn-sm" data-add-item="descarte">
                <i class="ri-add-line me-1"></i> Adicionar fruta
            </button>
        </div>
        <div data-items-container="descarte">
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
        <small class="text-muted">Cada fruta informada gera um lançamento de descarte com os dados de cabeçalho acima.</small>
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

<template id="descarte-item-template">
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
        const container = document.querySelector('[data-items-container="descarte"]');
        const addButton = document.querySelector('[data-add-item="descarte"]');
        const template = document.getElementById('descarte-item-template');
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
