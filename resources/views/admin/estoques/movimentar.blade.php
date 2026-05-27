@extends('layouts.app')

@section('title', 'Adicionar Estoque')
@section('page-title', 'Adicionar Estoque')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <h4 class="header-title mb-0">Adicionar Estoque</h4>
            <a href="{{ route('admin.estoques.unidade', $unidade) }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
        </div>
        <div class="card-body">
            @error('movimentacao')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror

            <div class="alert alert-light border mb-3">
                <span class="text-muted small d-block">Unidade de negócio</span>
                <strong>{{ $unidade->nome }}</strong>
                <span class="text-muted">(CIGAM {{ $unidade->id_cigam }})</span>
            </div>

            <form method="POST" action="{{ route('admin.estoques.movimentar.store') }}" class="row g-3" id="form-adicionar-estoque">
                @csrf
                <input type="hidden" name="id_unidade_negocio" value="{{ $unidade->id }}">

                @php
                    $itens = old('itens');
                    if (! is_array($itens) || $itens === []) {
                        $itens = [[
                            'id_fruta' => $idFrutaPreselecionada,
                            'qtd_fruta_um' => null,
                            'preco_fruta_um' => null,
                        ]];
                    }
                @endphp

                <div class="col-12">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h5 class="mb-0">Frutas</h5>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-add-item="estoque">
                            <i class="ri-add-line me-1"></i> Adicionar fruta
                        </button>
                    </div>
                    <div data-items-container="estoque">
                        @foreach ($itens as $i => $item)
                            <div class="row g-2 mb-2" data-item-row>
                                <div class="col-md-5">
                                    <select name="itens[{{ $i }}][id_fruta]"
                                            class="form-select @error("itens.$i.id_fruta") is-invalid @enderror"
                                            data-search-select
                                            data-placeholder="Selecione ou pesquise a fruta"
                                            required>
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
                                    <input type="text"
                                           name="itens[{{ $i }}][qtd_fruta_um]"
                                           data-mask-decimal-br
                                           value="{{ $item['qtd_fruta_um'] ?? '' }}"
                                           class="form-control @error("itens.$i.qtd_fruta_um") is-invalid @enderror"
                                           inputmode="decimal"
                                           autocomplete="off"
                                           placeholder="Qtd (UM)"
                                           required>
                                    @error("itens.$i.qtd_fruta_um")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-3">
                                    <input type="text"
                                           name="itens[{{ $i }}][preco_fruta_um]"
                                           data-mask-price-br
                                           value="{{ $item['preco_fruta_um'] ?? '' }}"
                                           class="form-control @error("itens.$i.preco_fruta_um") is-invalid @enderror"
                                           inputmode="decimal"
                                           autocomplete="off"
                                           placeholder="Preço / UM"
                                           required>
                                    @error("itens.$i.preco_fruta_um")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-outline-danger w-100" data-remove-item aria-label="Remover item">&times;</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <small class="text-muted">Quantidade e preço na unidade de medição de cada fruta (ex.: caixa, fardo). O sistema converte para kg internamente.</small>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="ri-check-line me-1"></i> Registrar entradas
                    </button>
                </div>
            </form>
        </div>
    </div>

    <template id="estoque-item-template">
        <div class="row g-2 mb-2" data-item-row>
            <div class="col-md-5">
                <select name="itens[__INDEX__][id_fruta]" class="form-select" data-search-select data-placeholder="Selecione ou pesquise a fruta" required>
                    <option value="">Fruta</option>
                    @foreach ($frutas as $fruta)
                        <option value="{{ $fruta->id }}">{{ $fruta->nome }} ({{ $fruta->unidade_medicao }}) — {{ $fruta->kg_por_unidade_medicao }} kg/UM</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <input type="text" name="itens[__INDEX__][qtd_fruta_um]" data-mask-decimal-br class="form-control" inputmode="decimal" autocomplete="off" placeholder="Qtd (UM)" required>
            </div>
            <div class="col-md-3">
                <input type="text" name="itens[__INDEX__][preco_fruta_um]" data-mask-price-br class="form-control" inputmode="decimal" autocomplete="off" placeholder="Preço / UM" required>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-outline-danger w-100" data-remove-item aria-label="Remover item">&times;</button>
            </div>
        </div>
    </template>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/admin-decimal-mask.js') }}"></script>
    @include('partials.admin.search-select-init')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const container = document.querySelector('[data-items-container="estoque"]');
            const addButton = document.querySelector('[data-add-item="estoque"]');
            const template = document.getElementById('estoque-item-template');
            if (!container || !addButton || !template) return;

            const refreshRemoveButtons = () => {
                container.querySelectorAll('[data-remove-item]').forEach((button) => {
                    button.disabled = container.querySelectorAll('[data-item-row]').length <= 1;
                });
            };

            addButton.addEventListener('click', () => {
                const index = container.querySelectorAll('[data-item-row]').length;
                container.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(index)));
                window.AdminSearchSelect?.init(container.lastElementChild);
                if (window.AdminDecimalMask?.init) {
                    window.AdminDecimalMask.init(container.lastElementChild);
                }
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

            refreshRemoveButtons();
        });
    </script>
@endpush
