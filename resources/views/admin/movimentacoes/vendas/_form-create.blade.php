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
            <label class="form-label">Origem física <span class="text-danger">*</span></label>
            <select name="id_empresa_origem" id="id_empresa_origem" class="form-select" required data-venda-origem data-search-select data-placeholder="Selecione ou pesquise a origem física">
                <option value="">Selecione</option>
                @foreach ($opcoes['empresas_origem'] as $empresa)
                    <option value="{{ $empresa->id }}" data-is-hub="{{ $empresa->entidade?->is_hub ? '1' : '0' }}" @selected((int) old('id_empresa_origem', $movimentacao->id_empresa_origem ?? 0) === $empresa->id)>
                        {{ $empresa->nomeExibicao() }}
                    </option>
                @endforeach
            </select>
            <small class="text-muted">Unidade de onde sai o estoque. As frutas dos itens dependem deste campo — não do cliente.</small>
        </div>
        <div class="col-md-3">
            <label class="form-label">Cliente destino</label>
            <select name="id_empresa_destino" class="form-select" required data-search-select data-placeholder="Selecione ou pesquise o cliente">
                <option value="">Selecione</option>
                @foreach ($opcoes['empresas_destino_cliente'] as $empresa)
                    <option value="{{ $empresa->id }}" @selected((int) old('id_empresa_destino', $movimentacao->id_empresa_destino ?? 0) === $empresa->id)>
                        {{ $empresa->nomeExibicao() }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3" data-venda-faturamento-wrapper>
            <label class="form-label">Unidade faturamento</label>
            <select name="id_unidade_negocio_faturamento" class="form-select" data-venda-faturamento data-search-select data-placeholder="Selecione ou pesquise a unidade de faturamento">
                <option value="">Selecione</option>
                @foreach ($opcoes['unidades_faturamento'] as $unidade)
                    <option value="{{ $unidade->id }}" @selected((int) old('id_unidade_negocio_faturamento', $movimentacao->id_unidade_negocio_faturamento ?? 0) === $unidade->id)>
                        {{ $unidade->nome }}
                    </option>
                @endforeach
            </select>
            <small class="text-muted">Informe apenas quando a origem física for HUB. Caso contrário, a própria origem será usada automaticamente.</small>
        </div>
        <div class="col-md-3">
            <label class="form-label">Frete</label>
            <select name="id_frete" class="form-select" data-search-select data-placeholder="Selecione ou pesquise o frete">
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
                <select name="id_fruta" class="form-select" required data-fruta-select data-search-select data-placeholder="Selecione ou pesquise a fruta">
                    @foreach ($opcoes['frutas'] as $fruta)
                        <option value="{{ $fruta->id }}" data-estoque-origens="{{ implode(',', $fruta->estoque_origem_empresa_ids ?? []) }}" @selected((int) old('id_fruta', $movimentacao->id_fruta) === $fruta->id)>{{ $fruta->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Quantidade UM</label>
                <input name="qtd_fruta_um" class="form-control" required value="{{ old('qtd_fruta_um', number_format((float) $movimentacao->qtd_fruta_um, 2, '.', '')) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Valor vendido</label>
                @php
                    $valorVendido = old('valor_nf_total');
                    if ($valorVendido === null) {
                        $valorVendido = number_format((float) $movimentacao->valor_nf_total, 2, ',', '.');
                    }
                @endphp
                <input type="text"
                       name="valor_nf_total"
                       class="form-control @error('valor_nf_total') is-invalid @enderror"
                       data-mask-decimal-br-cents
                       inputmode="numeric"
                       autocomplete="off"
                       placeholder=""
                       required
                       value="{{ $valorVendido }}">
            </div>
            <div class="col-md-12">
                <label class="form-label">Motivo da correção</label>
                <textarea name="motivo_substituicao" class="form-control" rows="2">{{ old('motivo_substituicao') }}</textarea>
            </div>
        </div>
    @else
        @php
            $itens = old('itens', [['id_fruta' => null, 'qtd_fruta_um' => null, 'valor_nf_total' => null]]);
        @endphp
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="mb-0">Itens</h5>
            <button type="button" class="btn btn-outline-primary btn-sm" data-add-item="venda">
                <i class="ri-add-line me-1"></i> Adicionar fruta
            </button>
        </div>
        <p class="small mb-2 text-muted" data-venda-fruta-aviso role="status">Escolha a <strong>origem física</strong> para liberar as frutas com estoque nessa unidade.</p>
        <div data-items-container="venda">
            @foreach ($itens as $i => $item)
                <div class="row g-3 mb-2" data-item-row>
                    <div class="col-md-5">
                        <select name="itens[{{ $i }}][id_fruta]" class="form-select" required data-fruta-select data-search-select data-placeholder="Selecione ou pesquise a fruta">
                            <option value="">Fruta</option>
                            @foreach ($opcoes['frutas'] as $fruta)
                                <option value="{{ $fruta->id }}" data-estoque-origens="{{ implode(',', $fruta->estoque_origem_empresa_ids ?? []) }}" @selected((int) ($item['id_fruta'] ?? 0) === $fruta->id)>{{ $fruta->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input name="itens[{{ $i }}][qtd_fruta_um]" class="form-control" placeholder="Qtd UM" value="{{ $item['qtd_fruta_um'] ?? '' }}" required>
                    </div>
                    <div class="col-md-3">
                        <input type="text"
                               name="itens[{{ $i }}][valor_nf_total]"
                               class="form-control @error("itens.$i.valor_nf_total") is-invalid @enderror"
                               data-mask-decimal-br-cents
                               inputmode="numeric"
                               autocomplete="off"
                               placeholder="R$ total"
                               value="{{ $item['valor_nf_total'] ?? '' }}"
                               required>
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-outline-danger w-100" data-remove-item aria-label="Remover item">&times;</button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit">{{ $isEdit ? 'Salvar nova versão' : 'Registrar venda' }}</button>
        <a href="{{ route('admin.movimentacoes.vendas.index') }}" class="btn btn-light">Cancelar</a>
    </div>
</form>

@unless ($isEdit)
    <template id="venda-item-template">
        <div class="row g-3 mb-2" data-item-row>
            <div class="col-md-5">
                <select name="itens[__INDEX__][id_fruta]" class="form-select" required data-fruta-select data-search-select data-placeholder="Selecione ou pesquise a fruta">
                    <option value="">Fruta</option>
                    @foreach ($opcoes['frutas'] as $fruta)
                        <option value="{{ $fruta->id }}" data-estoque-origens="{{ implode(',', $fruta->estoque_origem_empresa_ids ?? []) }}">{{ $fruta->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <input name="itens[__INDEX__][qtd_fruta_um]" class="form-control" placeholder="Qtd UM" required>
            </div>
            <div class="col-md-3">
                <input type="text"
                       name="itens[__INDEX__][valor_nf_total]"
                       class="form-control"
                       data-mask-decimal-br-cents
                       inputmode="numeric"
                       autocomplete="off"
                       placeholder="R$ total"
                       required>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-outline-danger w-100" data-remove-item aria-label="Remover item">&times;</button>
            </div>
        </div>
    </template>
@endunless

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const origem = document.querySelector('[data-venda-origem]');
        const wrapper = document.querySelector('[data-venda-faturamento-wrapper]');
        const faturamento = document.querySelector('[data-venda-faturamento]');
        const avisoFruta = document.querySelector('[data-venda-fruta-aviso]');

        const reinitFrutaSelect = (select) => {
            if (!window.jQuery || !select) {
                return;
            }

            const $select = window.jQuery(select);

            if ($select.hasClass('select2-hidden-accessible')) {
                $select.select2('destroy');
            }

            window.AdminSearchSelect?.init(select);
        };

        const filtrarFrutasPorOrigem = () => {
            if (!origem) {
                return;
            }

            const origemId = String(origem.value || '').trim();
            let temFrutaDisponivel = false;

            document.querySelectorAll('[data-fruta-select]').forEach((select) => {
                select.querySelectorAll('option').forEach((option) => {
                    if (!option.value) {
                        return;
                    }

                    const origens = (option.dataset.estoqueOrigens || '')
                        .split(',')
                        .map((id) => id.trim())
                        .filter((id) => id !== '');
                    const permitido = origemId !== '' && origens.includes(origemId);
                    option.hidden = !permitido;
                    option.disabled = !permitido;

                    if (permitido) {
                        temFrutaDisponivel = true;
                    }
                });

                if (select.selectedOptions.length && select.selectedOptions[0].disabled) {
                    select.value = '';
                }

                reinitFrutaSelect(select);
            });

            if (!avisoFruta) {
                return;
            }

            if (origemId === '') {
                avisoFruta.className = 'small mb-2 text-muted';
                avisoFruta.innerHTML = 'Escolha a <strong>origem física</strong> (não o cliente) para liberar as frutas com estoque nessa unidade.';
            } else if (!temFrutaDisponivel) {
                avisoFruta.className = 'small mb-2 text-danger';
                avisoFruta.textContent = 'Nenhuma fruta com estoque nesta origem física. Verifique o estoque da unidade ou selecione outra origem.';
            } else {
                avisoFruta.className = 'small mb-2 text-success';
                avisoFruta.textContent = 'Frutas liberadas para a origem física selecionada.';
            }
        };

        const onOrigemAlterada = () => {
            if (wrapper && faturamento) {
                const selecionada = origem.options[origem.selectedIndex];
                const origemEhHub = selecionada?.dataset?.isHub === '1';

                wrapper.classList.toggle('d-none', !origemEhHub);
                faturamento.required = origemEhHub;
                faturamento.disabled = !origemEhHub;

                if (!origemEhHub) {
                    faturamento.value = '';
                }

                window.AdminSearchSelect?.refresh(faturamento);
            }

            filtrarFrutasPorOrigem();
        };

        if (origem) {
            origem.addEventListener('change', onOrigemAlterada);

            if (window.jQuery?.fn?.select2) {
                window.jQuery(origem).on('change.select2.vendaOrigem', onOrigemAlterada);
            }
        }

        const container = document.querySelector('[data-items-container="venda"]');
        const addButton = document.querySelector('[data-add-item="venda"]');
        const template = document.getElementById('venda-item-template');
        if (!container || !addButton || !template) {
            return;
        }

        const refreshRemoveButtons = () => {
            container.querySelectorAll('[data-remove-item]').forEach((button) => {
                button.disabled = container.querySelectorAll('[data-item-row]').length <= 1;
            });
        };

        addButton.addEventListener('click', () => {
            const index = container.querySelectorAll('[data-item-row]').length;
            container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
            filtrarFrutasPorOrigem();
            if (container.lastElementChild) {
                window.AdminSearchSelect?.init(container.lastElementChild);
                if (window.AdminDecimalMask) {
                    window.AdminDecimalMask.bindCentsIn(container.lastElementChild);
                }
            }
            refreshRemoveButtons();
        });

        container.addEventListener('click', (event) => {
            if (!event.target.matches('[data-remove-item]')) {
                return;
            }
            const row = event.target.closest('[data-item-row]');
            if (row) {
                window.AdminSearchSelect?.destroy(row);
            }
            row?.remove();
            refreshRemoveButtons();
        });

        refreshRemoveButtons();

        // Select2 do layout inicia depois deste script; refiltra após init e se origem já veio do old().
        setTimeout(onOrigemAlterada, 0);
    });
</script>
