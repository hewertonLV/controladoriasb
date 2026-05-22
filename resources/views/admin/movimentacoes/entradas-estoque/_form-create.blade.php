<form method="post" action="{{ route('admin.movimentacoes.entradas-estoque.store') }}" class="row g-3">
    @csrf

    <div class="col-md-6">
        <label for="id_empresa_origem" class="form-label">Unidade de negócio <span class="text-danger">*</span></label>
        <select name="id_empresa_origem" id="id_empresa_origem" class="form-select @error('id_empresa_origem') is-invalid @enderror" data-search-select data-placeholder="Selecione a unidade" required>
            <option value="">Selecione…</option>
            @foreach ($empresas_unidade as $e)
                <option value="{{ $e->id }}" @selected(old('id_empresa_origem') == $e->id)>{{ $e->nomeExibicao() }}</option>
            @endforeach
        </select>
        @error('id_empresa_origem')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    @php
        $itens = old('itens', [[
            'id_fruta' => old('id_fruta'),
            'qtd_fruta_um' => old('qtd_fruta_um'),
            'preco_fruta_um' => old('preco_fruta_um'),
        ]]);
    @endphp

    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="mb-0">Itens</h5>
            <button type="button" class="btn btn-outline-primary btn-sm" data-add-item="entrada-estoque">
                <i class="ri-add-line me-1"></i> Adicionar fruta
            </button>
        </div>
        <div data-items-container="entrada-estoque">
            @foreach ($itens as $i => $item)
                <div class="row g-2 mb-2" data-item-row>
                    <div class="col-md-5">
                        <select name="itens[{{ $i }}][id_fruta]" class="form-select @error("itens.$i.id_fruta") is-invalid @enderror" required data-search-select data-placeholder="Fruta">
                            <option value="">Fruta</option>
                            @foreach ($frutas as $f)
                                <option value="{{ $f->id }}" @selected((string) ($item['id_fruta'] ?? '') === (string) $f->id)>
                                    {{ $f->nome }} ({{ $f->unidade_medicao }})
                                </option>
                            @endforeach
                        </select>
                        @error("itens.$i.id_fruta")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="itens[{{ $i }}][qtd_fruta_um]" data-mask-integer-br value="{{ $item['qtd_fruta_um'] ?? '' }}" class="form-control @error("itens.$i.qtd_fruta_um") is-invalid @enderror" inputmode="numeric" autocomplete="off" placeholder="Qtd UM" required>
                        @error("itens.$i.qtd_fruta_um")<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="itens[{{ $i }}][preco_fruta_um]" data-mask-price-br value="{{ $item['preco_fruta_um'] ?? '' }}" class="form-control @error("itens.$i.preco_fruta_um") is-invalid @enderror" inputmode="decimal" autocomplete="off" placeholder="Preço / UM" required>
                        @error("itens.$i.preco_fruta_um")<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-outline-danger w-100" data-remove-item aria-label="Remover item">&times;</button>
                    </div>
                </div>
            @endforeach
        </div>
        <small class="text-muted">Qtd UM: número inteiro (ex.: 100 ou 1.000). Preço: digite só números (ex.: 50 vira 50,00); use vírgula para centavos (ex.: 50,75).</small>
    </div>

    <div class="col-12">
        <label for="observacao" class="form-label">Observação</label>
        <textarea name="observacao" id="observacao" rows="2" class="form-control @error('observacao') is-invalid @enderror">{{ old('observacao') }}</textarea>
        @error('observacao')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">Salvar entrada</button>
    </div>
</form>

<template id="entrada-estoque-item-template">
    <div class="row g-2 mb-2" data-item-row>
        <div class="col-md-5">
            <select name="itens[__INDEX__][id_fruta]" class="form-select" required data-search-select data-placeholder="Fruta">
                <option value="">Fruta</option>
                @foreach ($frutas as $f)
                    <option value="{{ $f->id }}">{{ $f->nome }} ({{ $f->unidade_medicao }})</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" name="itens[__INDEX__][qtd_fruta_um]" data-mask-integer-br class="form-control" inputmode="numeric" autocomplete="off" placeholder="Qtd UM" required>
        </div>
        <div class="col-md-3">
            <input type="text" name="itens[__INDEX__][preco_fruta_um]" data-mask-price-br class="form-control" inputmode="decimal" autocomplete="off" placeholder="Preço / UM" required>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger w-100" data-remove-item aria-label="Remover item">&times;</button>
        </div>
    </div>
</template>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.querySelector('[data-items-container="entrada-estoque"]');
        const addButton = document.querySelector('[data-add-item="entrada-estoque"]');
        const template = document.getElementById('entrada-estoque-item-template');
        if (!container || !addButton || !template) return;

        const refreshRemoveButtons = () => {
            container.querySelectorAll('[data-remove-item]').forEach((button) => {
                button.disabled = container.querySelectorAll('[data-item-row]').length <= 1;
            });
        };

        addButton.addEventListener('click', () => {
            const index = container.querySelectorAll('[data-item-row]').length;
            container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
            if (container.lastElementChild) {
                window.AdminSearchSelect?.init(container.lastElementChild);
                window.AdminDecimalMask?.init(container.lastElementChild);
            }
            refreshRemoveButtons();
        });

        container.addEventListener('click', (event) => {
            if (!event.target.matches('[data-remove-item]')) return;
            const row = event.target.closest('[data-item-row]');
            if (row) window.AdminSearchSelect?.destroy(row);
            row?.remove();
            refreshRemoveButtons();
        });

        refreshRemoveButtons();
    });
</script>
