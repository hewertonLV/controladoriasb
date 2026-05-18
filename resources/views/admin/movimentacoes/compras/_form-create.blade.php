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
        <select name="id_empresa_origem" id="id_empresa_origem" class="form-select @error('id_empresa_origem') is-invalid @enderror" data-compra-search-select data-placeholder="Selecione ou pesquise a fornecedora" required>
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
        <select name="id_empresa_destino" id="id_empresa_destino" class="form-select @error('id_empresa_destino') is-invalid @enderror" data-compra-search-select data-placeholder="Selecione ou pesquise a unidade" required>
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
        <label for="id_frete" class="form-label">Frete <span class="text-danger">*</span></label>
        <select name="id_frete" id="id_frete" class="form-select @error('id_frete') is-invalid @enderror" data-compra-search-select data-placeholder="Selecione ou pesquise o frete" required>
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
        <label for="numero_nf_origem" class="form-label">Número da NF de compra</label>
        <input type="text"
               name="numero_nf_origem"
               id="numero_nf_origem"
               value="{{ old('numero_nf_origem') }}"
               class="form-control @error('numero_nf_origem') is-invalid @enderror"
               maxlength="120"
               inputmode="numeric"
               pattern="[0-9]*"
               autocomplete="off"
               placeholder="Ex.: 12345">
        @error('numero_nf_origem')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <small class="text-muted">Informe somente números. Esse número será replicado para todos os itens da compra.</small>
    </div>

    @php
        $itens = old('itens', [[
            'id_fruta' => old('id_fruta'),
            'qtd_fruta_um' => old('qtd_fruta_um'),
            'valor_nf_total' => old('valor_nf_total'),
        ]]);
    @endphp

    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="mb-0">Itens</h5>
            <button type="button" class="btn btn-outline-primary btn-sm" data-add-item="compra">
                <i class="ri-add-line me-1"></i> Adicionar fruta
            </button>
        </div>
        <div data-items-container="compra">
            @foreach ($itens as $i => $item)
                <div class="row g-2 mb-2" data-item-row>
                    <div class="col-md-5">
                        <select name="itens[{{ $i }}][id_fruta]" class="form-select @error("itens.$i.id_fruta") is-invalid @enderror" data-compra-search-select data-placeholder="Selecione ou pesquise a fruta" required>
                            <option value="">Fruta</option>
                            @foreach ($frutas as $fruta)
                                <option value="{{ $fruta->id }}" @selected((string) ($item['id_fruta'] ?? '') === (string) $fruta->id)>
                                    {{ $fruta->nome }} ({{ $fruta->unidade_medicao }}) — {{ $fruta->kg_por_unidade_medicao }} kg/UM
                                </option>
                            @endforeach
                        </select>
                        @error("itens.$i.id_fruta")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="itens[{{ $i }}][qtd_fruta_um]" data-mask-decimal-br value="{{ $item['qtd_fruta_um'] ?? '' }}" class="form-control @error("itens.$i.qtd_fruta_um") is-invalid @enderror" inputmode="decimal" autocomplete="off" placeholder="Qtd UM" required>
                        @error("itens.$i.qtd_fruta_um")<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <input type="text" name="itens[{{ $i }}][valor_nf_total]" data-mask-money-br value="{{ $item['valor_nf_total'] ?? '' }}" class="form-control @error("itens.$i.valor_nf_total") is-invalid @enderror" inputmode="decimal" autocomplete="off" placeholder="Valor NF total" required>
                        @error("itens.$i.valor_nf_total")<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-outline-danger w-100" data-remove-item aria-label="Remover item">&times;</button>
                    </div>
                </div>
            @endforeach
        </div>
        <small class="text-muted">Use vírgula para decimais. Cada fruta informada gera um lançamento de compra.</small>
    </div>

    <div class="col-12 d-flex justify-content-end gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="ri-check-line me-1"></i> Salvar compra
        </button>
    </div>
</form>

<template id="compra-item-template">
    <div class="row g-2 mb-2" data-item-row>
        <div class="col-md-5">
            <select name="itens[__INDEX__][id_fruta]" class="form-select" data-compra-search-select data-placeholder="Selecione ou pesquise a fruta" required>
                <option value="">Fruta</option>
                @foreach ($frutas as $fruta)
                    <option value="{{ $fruta->id }}">{{ $fruta->nome }} ({{ $fruta->unidade_medicao }}) — {{ $fruta->kg_por_unidade_medicao }} kg/UM</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <input type="text" name="itens[__INDEX__][qtd_fruta_um]" data-mask-decimal-br class="form-control" inputmode="decimal" autocomplete="off" placeholder="Qtd UM" required>
        </div>
        <div class="col-md-3">
            <input type="text" name="itens[__INDEX__][valor_nf_total]" data-mask-money-br class="form-control" inputmode="decimal" autocomplete="off" placeholder="Valor NF total" required>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger w-100" data-remove-item aria-label="Remover item">&times;</button>
        </div>
    </div>
</template>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.querySelector('[data-items-container="compra"]');
        const addButton = document.querySelector('[data-add-item="compra"]');
        const template = document.getElementById('compra-item-template');
        if (!container || !addButton || !template) return;

        const initSearchableSelects = (root = document) => {
            if (!window.jQuery || !window.jQuery.fn.select2) {
                return;
            }

            window.jQuery(root).find('[data-compra-search-select]').each(function () {
                const select = window.jQuery(this);

                if (select.hasClass('select2-hidden-accessible')) {
                    return;
                }

                select.select2({
                    allowClear: !this.required,
                    language: {
                        noResults: () => 'Nenhum resultado encontrado',
                        searching: () => 'Pesquisando…',
                    },
                    placeholder: this.dataset.placeholder || 'Selecione…',
                    width: '100%',
                });
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
            initSearchableSelects(container.lastElementChild);
            refreshRemoveButtons();
        });

        container.addEventListener('click', (event) => {
            if (!event.target.matches('[data-remove-item]')) return;
            const row = event.target.closest('[data-item-row]');

            if (window.jQuery && window.jQuery.fn.select2) {
                window.jQuery(row).find('.select2-hidden-accessible').select2('destroy');
            }

            row?.remove();
            refreshRemoveButtons();
        });

        initSearchableSelects();
        refreshRemoveButtons();
    });
</script>
