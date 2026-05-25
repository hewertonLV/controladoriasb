@php
    /** @var \App\Models\Cliente $cliente */
    /** @var \Illuminate\Support\Collection<int, \App\Models\UnidadeNegocio> $unidadesNegocio */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Praca> $pracas */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Grupo> $grupos */

    $unidadeSelecionada = (int) old('id_unidade_negocio', $cliente->id_unidade_negocio ?: 0);
    $pracaSelecionada = (int) old('id_praca', $cliente->id_praca ?: 0);
@endphp

<div class="card">
    <div class="card-header">
        <h4 class="header-title mb-0">{{ $cardTitle ?? 'Dados do cliente' }}</h4>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label for="id_cigam" class="form-label">ID CIGAM <span class="text-danger">*</span></label>
                <input type="text"
                       id="id_cigam"
                       name="id_cigam"
                       value="{{ old('id_cigam', $cliente->id_cigam) }}"
                       class="form-control @error('id_cigam') is-invalid @enderror"
                       maxlength="6"
                       required
                       autofocus
                       placeholder="Ex.: 1 => 000001"
                       autocomplete="off">
                @error('id_cigam')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
                <small class="text-muted">Armazenado somente com dígitos (6 caracteres, com zeros à esquerda).</small>
            </div>
            <div class="col-md-8">
                <label for="razao_social" class="form-label">Razão social <span class="text-danger">*</span></label>
                <input type="text"
                       id="razao_social"
                       name="razao_social"
                       value="{{ old('razao_social', $cliente->razao_social) }}"
                       class="form-control @error('razao_social') is-invalid @enderror"
                       maxlength="255"
                       required
                       placeholder="Razão social"
                       autocomplete="off">
                @error('razao_social')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-8">
                <label for="fantasia" class="form-label">Fantasia</label>
                <input type="text"
                       id="fantasia"
                       name="fantasia"
                       value="{{ old('fantasia', $cliente->fantasia) }}"
                       class="form-control @error('fantasia') is-invalid @enderror"
                       maxlength="255"
                       placeholder="Nome fantasia"
                       autocomplete="off">
                @error('fantasia')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4">
                <label for="cnpj_cpf" class="form-label">CPF/CNPJ <span class="text-danger">*</span></label>
                <input type="text"
                       id="cnpj_cpf"
                       name="cnpj_cpf"
                       value="{{ old('cnpj_cpf', $cliente->cnpj_cpf_formatado) }}"
                       class="form-control @error('cnpj_cpf') is-invalid @enderror"
                       maxlength="18"
                       required
                       placeholder="Apenas dígitos (ou com máscara)"
                       autocomplete="off">
                @error('cnpj_cpf')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4">
                <label for="id_unidade_negocio" class="form-label">Unidade de Negócio <span class="text-danger">*</span></label>
                <select id="id_unidade_negocio"
                        name="id_unidade_negocio"
                        class="form-select @error('id_unidade_negocio') is-invalid @enderror"
                        required>
                    <option value="">Selecione...</option>
                    @foreach ($unidadesNegocio as $unidade)
                        <option value="{{ $unidade->id }}"
                            @selected((int) old('id_unidade_negocio', $cliente->id_unidade_negocio) === (int) $unidade->id)>
                            {{ $unidade->nome }} ({{ $unidade->id_cigam }})
                        </option>
                    @endforeach
                </select>
                @error('id_unidade_negocio')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4">
                <label for="id_praca" class="form-label">Praça <span class="text-danger">*</span></label>
                <select id="id_praca"
                        name="id_praca"
                        class="form-select @error('id_praca') is-invalid @enderror"
                        data-praca-selecionada="{{ $pracaSelecionada > 0 ? $pracaSelecionada : '' }}"
                        @disabled($unidadeSelecionada < 1)
                        required>
                    <option value="">
                        {{ $unidadeSelecionada < 1 ? 'Selecione a unidade de negócio' : 'Selecione...' }}
                    </option>
                    @if ($unidadeSelecionada > 0)
                        @foreach ($pracas->where('id_unidade_negocio', $unidadeSelecionada) as $praca)
                            <option value="{{ $praca->id }}"
                                @selected($pracaSelecionada === $praca->id)>
                                {{ $praca->nome }}
                            </option>
                        @endforeach
                    @endif
                </select>
                @error('id_praca')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4">
                <label for="grupo_id" class="form-label">Grupo</label>
                <select id="grupo_id"
                        name="grupo_id"
                        class="form-select @error('grupo_id') is-invalid @enderror">
                    <option value="">Sem grupo</option>
                    @foreach ($grupos as $grupo)
                        <option value="{{ $grupo->id }}"
                                @selected((int) old('grupo_id', $cliente->grupo_id) === $grupo->id)>
                            {{ $grupo->nome }}
                        </option>
                    @endforeach
                </select>
                @error('grupo_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            @isset($carteirasCaptacao)
                <div class="col-md-6">
                    <label for="id_captacao_carteira" class="form-label">Carteira de captação</label>
                    <select id="id_captacao_carteira" name="id_captacao_carteira" class="form-select @error('id_captacao_carteira') is-invalid @enderror">
                        <option value="">Sem carteira</option>
                        @foreach ($carteirasCaptacao as $carteira)
                            <option value="{{ $carteira->id }}" @selected((int) old('id_captacao_carteira', $cliente->id_captacao_carteira) === $carteira->id)>
                                {{ $carteira->nome }}
                            </option>
                        @endforeach
                    </select>
                    @error('id_captacao_carteira')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            @endisset
            <div class="col-md-4">
                <label for="desconto_nf" class="form-label">Desconto NF <span class="text-danger">*</span></label>
                <input type="number"
                       id="desconto_nf"
                       name="desconto_nf"
                       value="{{ old('desconto_nf', $cliente->desconto_nf ?? '0.00') }}"
                       class="form-control @error('desconto_nf') is-invalid @enderror"
                       min="0"
                       step="0.01"
                       required>
                @error('desconto_nf')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="col-md-4">
                <label for="percentual_margem_alvo" class="form-label">Margem alvo captação (%)</label>
                <input type="number"
                       id="percentual_margem_alvo"
                       name="percentual_margem_alvo"
                       value="{{ old('percentual_margem_alvo', $cliente->percentual_margem_alvo ?? '') }}"
                       class="form-control @error('percentual_margem_alvo') is-invalid @enderror"
                       min="0"
                       max="99.99"
                       step="0.01"
                       placeholder="Ex.: 30">
                <div class="form-text">Sobre o preço de venda; usado para sugerir preço ideal na captação por loja.</div>
                @error('percentual_margem_alvo')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>

    @isset($diasSemanaCaptacao)
        <div class="card mt-3">
            <div class="card-header"><strong>Agenda de captação</strong></div>
            <div class="card-body">
                <p class="text-muted small">Dias da semana em que o pedido costuma ser <strong>criado</strong> e <strong>enviado</strong> (0 = domingo).</p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <span class="form-label d-block">Dias de criação do pedido</span>
                        @foreach ($diasSemanaCaptacao as $dia => $label)
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="dias_criacao_pedido[]" id="dia_criacao_{{ $dia }}" value="{{ $dia }}"
                                       @checked(in_array($dia, old('dias_criacao_pedido', $diasCriacaoPedido ?? []), true))>
                                <label class="form-check-label" for="dia_criacao_{{ $dia }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                    <div class="col-md-6">
                        <span class="form-label d-block">Dias de envio do pedido</span>
                        @foreach ($diasSemanaCaptacao as $dia => $label)
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="dias_envio_pedido[]" id="dia_envio_{{ $dia }}" value="{{ $dia }}"
                                       @checked(in_array($dia, old('dias_envio_pedido', $diasEnvioPedido ?? []), true))>
                                <label class="form-check-label" for="dia_envio_{{ $dia }}">{{ $label }}</label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    @endisset

    <div class="card-footer d-flex gap-2 justify-content-end">
        <a href="{{ route('admin.clientes.index') }}" class="btn btn-light">
            <i class="ri-arrow-left-line me-1"></i> Voltar
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="ri-save-line me-1"></i> {{ $submitLabel ?? 'Salvar' }}
        </button>
    </div>
</div>

<template id="cliente-pracas-opcoes">
    @foreach ($pracas as $praca)
        <option value="{{ $praca->id }}" data-unidade="{{ $praca->id_unidade_negocio }}">
            {{ $praca->nome }}
        </option>
    @endforeach
</template>

@push('scripts')
    <script>
        (function () {
            const unidadeSelect = document.getElementById('id_unidade_negocio');
            const pracaSelect = document.getElementById('id_praca');
            const template = document.getElementById('cliente-pracas-opcoes');

            if (!unidadeSelect || !pracaSelect || !template) {
                return;
            }

            function atualizarPracas(resetSelecao) {
                const unidadeId = unidadeSelect.value;
                const pracaSelecionada = resetSelecao ? '' : (pracaSelect.dataset.pracaSelecionada || '');

                while (pracaSelect.options.length > 1) {
                    pracaSelect.remove(1);
                }

                if (!unidadeId) {
                    pracaSelect.value = '';
                    pracaSelect.disabled = true;
                    pracaSelect.options[0].textContent = 'Selecione a unidade de negócio';
                    return;
                }

                pracaSelect.disabled = false;
                pracaSelect.options[0].textContent = 'Selecione...';

                let selecionou = false;
                template.content.querySelectorAll('option[data-unidade]').forEach(function (option) {
                    if (option.dataset.unidade !== unidadeId) {
                        return;
                    }

                    const clone = option.cloneNode(true);
                    if (pracaSelecionada !== '' && clone.value === pracaSelecionada) {
                        clone.selected = true;
                        selecionou = true;
                    }
                    pracaSelect.appendChild(clone);
                });

                if (!selecionou) {
                    pracaSelect.value = '';
                }
            }

            unidadeSelect.addEventListener('change', function () {
                pracaSelect.dataset.pracaSelecionada = '';
                atualizarPracas(true);
            });

            atualizarPracas(false);
        })();
    </script>
@endpush
