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
            <select name="id_empresa_origem" class="form-select" required data-venda-origem>
                <option value="">Selecione</option>
                @foreach ($opcoes['empresas_origem'] as $empresa)
                    <option value="{{ $empresa->id }}" data-is-hub="{{ $empresa->entidade?->is_hub ? '1' : '0' }}" @selected((int) old('id_empresa_origem', $movimentacao->id_empresa_origem ?? 0) === $empresa->id)>
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
        <div class="col-md-3" data-venda-faturamento-wrapper>
            <label class="form-label">Unidade faturamento</label>
            <select name="id_unidade_negocio_faturamento" class="form-select" data-venda-faturamento>
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
        @php
            $itens = old('itens', [['id_fruta' => null, 'qtd_fruta_um' => null, 'valor_nf_total' => null]]);
        @endphp
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h5 class="mb-0">Itens</h5>
            <button type="button" class="btn btn-outline-primary btn-sm" data-add-item="venda">
                <i class="ri-add-line me-1"></i> Adicionar fruta
            </button>
        </div>
        <div data-items-container="venda">
            @foreach ($itens as $i => $item)
                <div class="row g-3 mb-2" data-item-row>
                    <div class="col-md-5">
                        <select name="itens[{{ $i }}][id_fruta]" class="form-select" required>
                            <option value="">Fruta</option>
                            @foreach ($opcoes['frutas'] as $fruta)
                                <option value="{{ $fruta->id }}" @selected((int) ($item['id_fruta'] ?? 0) === $fruta->id)>{{ $fruta->nome }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input name="itens[{{ $i }}][qtd_fruta_um]" class="form-control" placeholder="Qtd UM" value="{{ $item['qtd_fruta_um'] ?? '' }}" required>
                    </div>
                    <div class="col-md-3">
                        <input name="itens[{{ $i }}][valor_nf_total]" class="form-control money-mask" placeholder="Valor vendido" value="{{ $item['valor_nf_total'] ?? '' }}" required>
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
                <select name="itens[__INDEX__][id_fruta]" class="form-select" required>
                    <option value="">Fruta</option>
                    @foreach ($opcoes['frutas'] as $fruta)
                        <option value="{{ $fruta->id }}">{{ $fruta->nome }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <input name="itens[__INDEX__][qtd_fruta_um]" class="form-control" placeholder="Qtd UM" required>
            </div>
            <div class="col-md-3">
                <input name="itens[__INDEX__][valor_nf_total]" class="form-control money-mask" placeholder="Valor vendido" required>
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

        if (!origem || !wrapper || !faturamento) {
            return;
        }

        const atualizarFaturamento = () => {
            const selecionada = origem.options[origem.selectedIndex];
            const origemEhHub = selecionada?.dataset?.isHub === '1';

            wrapper.classList.toggle('d-none', !origemEhHub);
            faturamento.required = origemEhHub;
            faturamento.disabled = !origemEhHub;

            if (!origemEhHub) {
                faturamento.value = '';
            }
        };

        origem.addEventListener('change', atualizarFaturamento);
        atualizarFaturamento();

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
            refreshRemoveButtons();
        });

        container.addEventListener('click', (event) => {
            if (!event.target.matches('[data-remove-item]')) {
                return;
            }
            event.target.closest('[data-item-row]')?.remove();
            refreshRemoveButtons();
        });

        refreshRemoveButtons();
    });
</script>
