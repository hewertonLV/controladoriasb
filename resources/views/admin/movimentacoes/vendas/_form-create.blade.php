@php
    $isEdit = isset($movimentacao);
    $opcoes = $opcoes ?? compact('empresas_origem', 'empresas_destino_cliente', 'unidades_estoque', 'unidades_hub', 'frutas', 'frutas_catalogo', 'fretes');
    $frutasCatalogo = $opcoes['frutas_catalogo'] ?? \App\Support\Movimentacoes\FrutasComEstoqueOrigem::catalogoJs($opcoes['frutas'] ?? collect());
    $hubCoHistorico = ($isEdit && ($movimentacao->id_custo_operacional ?? null))
        ? \App\Models\HistoricoCOUnNg::query()->find((int) $movimentacao->id_custo_operacional)
        : null;
    $aplicarHubCo = old('aplicar_custo_operacional_hub', $isEdit ? (bool) $hubCoHistorico : true);
    $hubCoSelecionado = old('id_unidade_negocio_hub_custo', $hubCoHistorico?->id_unidade_negocio);
@endphp

<form method="POST"
      action="{{ $isEdit ? route('admin.movimentacoes.vendas.update', $movimentacao) : route('admin.movimentacoes.vendas.store') }}"
      data-venda-form
      data-frutas-catalog='@json($frutasCatalogo)'>
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
            <label class="form-label">Origem comercial <span class="text-danger">*</span></label>
            <select name="id_empresa_origem" id="id_empresa_origem" class="form-select" required data-venda-comercial data-search-select data-placeholder="Selecione ou pesquise a loja comercial">
                <option value="">Selecione</option>
                @foreach ($opcoes['empresas_origem'] as $empresa)
                    <option value="{{ $empresa->id }}"
                            data-is-producao="{{ $empresa->entidade?->is_unidade_producao ? '1' : '0' }}"
                            @selected((int) old('id_empresa_origem', $movimentacao->id_empresa_origem ?? 0) === $empresa->id)>
                        {{ $empresa->nomeExibicao() }}
                    </option>
                @endforeach
            </select>
            <small class="text-muted">Loja que fatura e aparece nos relatórios (coluna B da planilha).</small>
        </div>
        <div class="col-md-3">
            <label class="form-label">Saída física (estoque)</label>
            <select name="id_unidade_negocio_estoque" class="form-select" data-venda-estoque data-search-select data-placeholder="Mesma da origem comercial">
                <option value="">Mesma da origem comercial</option>
                @foreach ($opcoes['unidades_estoque'] ?? [] as $unidade)
                    @php $empresaEstoque = $unidade->registroCorporativo()->first(); @endphp
                    <option value="{{ $unidade->id }}"
                            data-id-empresa="{{ $empresaEstoque?->id ?? '' }}"
                            data-is-hub="{{ $unidade->is_hub ? '1' : '0' }}"
                            @selected((int) old('id_unidade_negocio_estoque', $movimentacao->id_unidade_negocio_estoque ?? 0) === $unidade->id)>
                        {{ $unidade->nome }}{{ $unidade->is_hub ? ' · HUB' : '' }}
                    </option>
                @endforeach
            </select>
            <small class="text-muted">Unidade de onde a fruta sai fisicamente. Selecione o HUB para venda direta do centro de distribuição.</small>
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
        <div class="col-md-3 d-none" data-venda-hub-custo-wrapper>
            <div class="form-check form-switch mb-2">
                <input type="hidden" name="aplicar_custo_operacional_hub" value="0">
                <input type="checkbox"
                       class="form-check-input"
                       id="aplicar_custo_operacional_hub"
                       name="aplicar_custo_operacional_hub"
                       value="1"
                       data-venda-aplicar-hub-co
                       @checked($aplicarHubCo)>
                <label class="form-check-label" for="aplicar_custo_operacional_hub">Incluir custo operacional do HUB</label>
            </div>
            <div data-venda-hub-custo-select-wrapper @class(['d-none' => ! $aplicarHubCo])>
                <label class="form-label">Unidade HUB (custo)</label>
                <select @if($aplicarHubCo) name="id_unidade_negocio_hub_custo" @endif
                        class="form-select @error('id_unidade_negocio_hub_custo') is-invalid @enderror"
                        data-venda-hub-custo
                        data-search-select
                        data-placeholder="Selecione o HUB"
                        @disabled(! $aplicarHubCo)>
                    <option value="">Selecione</option>
                    @forelse ($opcoes['unidades_hub'] ?? [] as $hub)
                        <option value="{{ $hub->id }}" @selected((int) $hubCoSelecionado === $hub->id)>{{ $hub->nome }}</option>
                    @empty
                        <option value="" disabled>Nenhuma unidade marcada como HUB no cadastro</option>
                    @endforelse
                </select>
                @error('id_unidade_negocio_hub_custo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                @if (($opcoes['unidades_hub'] ?? collect())->isEmpty())
                    <small class="text-warning d-block">Cadastre em Unidades de negócio uma unidade com o switch <strong>Unidade HUB</strong> ativo.</small>
                @else
                    <small class="text-muted">CO do HUB entra na margem da venda, não no preço médio do estoque.</small>
                @endif
            </div>
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
                <select name="id_fruta"
                        class="form-select"
                        required
                        data-fruta-select
                        data-search-select
                        data-placeholder="Selecione ou pesquise a fruta"
                        data-selected-fruta="{{ old('id_fruta', $movimentacao->id_fruta) }}">
                    <option value="">Selecione ou pesquise a fruta</option>
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
        <p class="small mb-2 text-muted" data-venda-fruta-aviso role="status">Escolha a origem comercial e, se necessário, a saída física para liberar as frutas com estoque.</p>
        <div data-items-container="venda">
            @foreach ($itens as $i => $item)
                <div class="row g-3 mb-2" data-item-row>
                    <div class="col-md-5">
                        <select name="itens[{{ $i }}][id_fruta]"
                                class="form-select"
                                required
                                data-fruta-select
                                data-search-select
                                data-placeholder="Selecione ou pesquise a fruta"
                                data-selected-fruta="{{ $item['id_fruta'] ?? '' }}">
                            <option value="">Selecione ou pesquise a fruta</option>
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
                <select name="itens[__INDEX__][id_fruta]"
                        class="form-select"
                        required
                        data-fruta-select
                        data-search-select
                        data-placeholder="Selecione ou pesquise a fruta">
                    <option value="">Selecione ou pesquise a fruta</option>
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
        const comercial = document.querySelector('[data-venda-comercial]');
        const estoque = document.querySelector('[data-venda-estoque]');
        const hubCustoWrapper = document.querySelector('[data-venda-hub-custo-wrapper]');
        const aplicarHubCo = document.querySelector('[data-venda-aplicar-hub-co]');
        const hubCustoSelectWrapper = document.querySelector('[data-venda-hub-custo-select-wrapper]');
        const hubCustoSelect = document.querySelector('[data-venda-hub-custo]');
        const hubCustoFieldName = 'id_unidade_negocio_hub_custo';
        const avisoFruta = document.querySelector('[data-venda-fruta-aviso]');
        const vendaForm = document.querySelector('[data-venda-form]');
        let frutasCatalogo = [];

        try {
            frutasCatalogo = JSON.parse(vendaForm?.dataset?.frutasCatalog || '[]');
        } catch {
            frutasCatalogo = [];
        }

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

        const frutaPermitidaNaOrigem = (fruta, origemId) => {
            if (!origemId) {
                return false;
            }

            return (fruta.origens || []).map(String).includes(String(origemId));
        };

        const popularSelectFrutas = (select, origemId) => {
            const placeholder = select.dataset.placeholder || 'Selecione ou pesquise a fruta';
            const valorPreservar = String(
                select.dataset.selectedFruta || select.value || '',
            ).trim();

            select.innerHTML = `<option value="">${placeholder}</option>`;

            if (origemId === '') {
                select.value = '';
                delete select.dataset.selectedFruta;
                reinitFrutaSelect(select);

                return false;
            }

            let temFrutaDisponivel = false;

            frutasCatalogo.forEach((fruta) => {
                if (!frutaPermitidaNaOrigem(fruta, origemId)) {
                    return;
                }

                temFrutaDisponivel = true;
                const option = document.createElement('option');
                option.value = String(fruta.id);
                option.textContent = fruta.nome;

                if (String(fruta.id) === valorPreservar) {
                    option.selected = true;
                }

                select.appendChild(option);
            });

            if (valorPreservar && select.value !== valorPreservar) {
                select.value = '';
            }

            delete select.dataset.selectedFruta;
            reinitFrutaSelect(select);

            return temFrutaDisponivel;
        };

        const empresaEstoqueEfetivaId = () => {
            if (estoque && estoque.value) {
                const opt = estoque.options[estoque.selectedIndex];
                const idEmpresa = opt?.dataset?.idEmpresa;
                if (idEmpresa) {
                    return String(idEmpresa);
                }
            }

            return String(comercial?.value || '').trim();
        };

        const filtrarFrutasPorOrigem = () => {
            const origemId = empresaEstoqueEfetivaId();
            let temFrutaDisponivel = false;

            document.querySelectorAll('[data-fruta-select]').forEach((select) => {
                if (popularSelectFrutas(select, origemId)) {
                    temFrutaDisponivel = true;
                }
            });

            if (!avisoFruta) {
                return;
            }

            if (origemId === '') {
                avisoFruta.className = 'small mb-2 text-muted';
                avisoFruta.innerHTML = 'Escolha a <strong>origem comercial</strong> e, se necessário, a <strong>saída física</strong> para listar frutas com estoque.';
            } else if (!temFrutaDisponivel) {
                avisoFruta.className = 'small mb-2 text-danger';
                avisoFruta.textContent = 'Nenhuma fruta com estoque na saída física selecionada. Verifique o estoque ou selecione outra unidade.';
            } else {
                avisoFruta.className = 'small mb-2 text-success';
                avisoFruta.textContent = 'Somente frutas com estoque na saída física selecionada.';
            }
        };

        const onHubCustoToggle = () => {
            if (!hubCustoSelect || !aplicarHubCo) {
                return;
            }

            const ativo = aplicarHubCo.checked;

            if (hubCustoSelectWrapper) {
                hubCustoSelectWrapper.classList.toggle('d-none', !ativo);
            }

            if (ativo) {
                hubCustoSelect.setAttribute('name', hubCustoFieldName);
                hubCustoSelect.disabled = false;
                hubCustoSelect.required = true;
                window.AdminSearchSelect?.init(hubCustoSelect);
            } else {
                hubCustoSelect.removeAttribute('name');
                hubCustoSelect.required = false;
                hubCustoSelect.disabled = true;
                hubCustoSelect.value = '';
                if (window.jQuery?.fn?.select2 && window.jQuery(hubCustoSelect).hasClass('select2-hidden-accessible')) {
                    window.jQuery(hubCustoSelect).val('').trigger('change');
                }
                window.AdminSearchSelect?.refresh(hubCustoSelect);
            }
        };

        const onCamposAlterados = () => {
            if (hubCustoWrapper && aplicarHubCo && comercial && estoque) {
                const comercialOpt = comercial.options[comercial.selectedIndex];
                const estoqueOpt = estoque.value ? estoque.options[estoque.selectedIndex] : null;
                const origemEhProducao = comercialOpt?.dataset?.isProducao === '1';
                const estoqueEhHub = estoqueOpt?.dataset?.isHub === '1';
                const exibirCo = origemEhProducao && !estoqueEhHub;

                hubCustoWrapper.classList.toggle('d-none', !exibirCo);

                if (!exibirCo) {
                    aplicarHubCo.checked = false;
                    aplicarHubCo.disabled = true;
                } else {
                    aplicarHubCo.disabled = false;
                    if (!aplicarHubCo.dataset.userTouched) {
                        aplicarHubCo.checked = true;
                    }
                }

                onHubCustoToggle();
            }

            filtrarFrutasPorOrigem();
        };

        if (aplicarHubCo) {
            aplicarHubCo.addEventListener('change', () => {
                aplicarHubCo.dataset.userTouched = '1';
                onHubCustoToggle();
            });
        }

        [comercial, estoque].forEach((el) => {
            if (!el) {
                return;
            }
            el.addEventListener('change', onCamposAlterados);
            if (window.jQuery?.fn?.select2) {
                window.jQuery(el).on('change.select2.vendaCampos', onCamposAlterados);
            }
        });

        const container = document.querySelector('[data-items-container="venda"]');
        const addButton = document.querySelector('[data-add-item="venda"]');
        const template = document.getElementById('venda-item-template');
        if (!container || !addButton || !template) {
            setTimeout(onCamposAlterados, 0);
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
        setTimeout(onCamposAlterados, 0);
    });
</script>
