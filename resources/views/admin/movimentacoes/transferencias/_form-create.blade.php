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
        <select name="id_empresa_origem" id="id_empresa_origem" class="form-select @error('id_empresa_origem') is-invalid @enderror" data-search-select data-placeholder="Selecione ou pesquise a unidade de origem" required>
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
        <select name="id_empresa_destino" id="id_empresa_destino" class="form-select @error('id_empresa_destino') is-invalid @enderror" data-search-select data-placeholder="Selecione ou pesquise a unidade de destino" required>
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
        <label for="id_frete" class="form-label">Frete</label>
        <select name="id_frete" id="id_frete" class="form-select @error('id_frete') is-invalid @enderror" data-search-select data-placeholder="Selecione ou pesquise o frete">
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
        <label for="numero_nf_origem" class="form-label">Número NF origem</label>
        <input type="text" name="numero_nf_origem" id="numero_nf_origem" value="{{ old('numero_nf_origem') }}"
               class="form-control @error('numero_nf_origem') is-invalid @enderror" maxlength="120">
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
            <button type="button" class="btn btn-outline-primary btn-sm" data-add-item="transferencia">
                <i class="ri-add-line me-1"></i> Adicionar fruta
            </button>
        </div>
        <div data-items-container="transferencia">
            @foreach ($itens as $i => $item)
                <div class="row g-2 mb-2" data-item-row>
                    <div class="col-md-7">
                        <select name="itens[{{ $i }}][id_fruta]" class="form-select @error("itens.$i.id_fruta") is-invalid @enderror" required data-fruta-select data-search-select data-placeholder="Selecione ou pesquise a fruta">
                            <option value="">Fruta</option>
                            @foreach ($frutas as $fruta)
                                <option value="{{ $fruta->id }}" data-estoque-origens="{{ implode(',', $fruta->estoque_origem_empresa_ids ?? []) }}" @selected((string) ($item['id_fruta'] ?? '') === (string) $fruta->id)>
                                    {{ $fruta->nome }} ({{ $fruta->unidade_medicao }}) — {{ $fruta->kg_por_unidade_medicao }} kg/UM
                                </option>
                            @endforeach
                        </select>
                        @error("itens.$i.id_fruta")<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="itens[{{ $i }}][qtd_fruta_um]" data-mask-decimal-br value="{{ $item['qtd_fruta_um'] ?? '' }}" class="form-control @error("itens.$i.qtd_fruta_um") is-invalid @enderror" inputmode="decimal" autocomplete="off" placeholder="Qtd UM" required>
                        @error("itens.$i.qtd_fruta_um")<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-outline-danger w-100" data-remove-item aria-label="Remover item">&times;</button>
                    </div>
                </div>
            @endforeach
        </div>
        <small class="text-muted">Cada fruta informada gera uma transferência com saída na origem e entrada pendente no destino.</small>
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

<template id="transferencia-item-template">
    <div class="row g-2 mb-2" data-item-row>
        <div class="col-md-7">
            <select name="itens[__INDEX__][id_fruta]" class="form-select" required data-fruta-select data-search-select data-placeholder="Selecione ou pesquise a fruta">
                <option value="">Fruta</option>
                @foreach ($frutas as $fruta)
                    <option value="{{ $fruta->id }}" data-estoque-origens="{{ implode(',', $fruta->estoque_origem_empresa_ids ?? []) }}">{{ $fruta->nome }} ({{ $fruta->unidade_medicao }}) — {{ $fruta->kg_por_unidade_medicao }} kg/UM</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <input type="text" name="itens[__INDEX__][qtd_fruta_um]" data-mask-decimal-br class="form-control" inputmode="decimal" autocomplete="off" placeholder="Qtd UM" required>
        </div>
        <div class="col-md-1">
            <button type="button" class="btn btn-outline-danger w-100" data-remove-item aria-label="Remover item">&times;</button>
        </div>
    </div>
</template>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const container = document.querySelector('[data-items-container="transferencia"]');
        const addButton = document.querySelector('[data-add-item="transferencia"]');
        const template = document.getElementById('transferencia-item-template');
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

                window.AdminSearchSelect?.refresh(select);
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
            window.AdminSearchSelect?.init(container.lastElementChild);
            refreshRemoveButtons();
        });

        container.addEventListener('click', (event) => {
            if (!event.target.matches('[data-remove-item]')) return;
            const row = event.target.closest('[data-item-row]');
            if (row) {
                window.AdminSearchSelect?.destroy(row);
            }
            row?.remove();
            refreshRemoveButtons();
        });

        origem.addEventListener('change', filtrarFrutasPorOrigem);
        filtrarFrutasPorOrigem();
        refreshRemoveButtons();
    });
</script>
