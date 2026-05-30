@props([
    'carteiras',
    'dataReferencia' => null,
])

@php
    $reabrirModalComErros = $errors->any() && (old('id_captacao_carteira') !== null || old('data_referencia') !== null);
@endphp

<div class="modal fade"
     id="modal-criar-captacao"
     tabindex="-1"
     aria-labelledby="modal-criar-captacao-label"
     aria-hidden="true"
     @if ($reabrirModalComErros) data-reopen-on-error="1" @endif>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="{{ route('admin.captacao.lotes.store') }}" id="form-criar-captacao">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-criar-captacao-label">Criar Captação</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Informe a data e a carteira da captação que deseja abrir.
                    </p>
                    <div class="mb-3">
                        <label class="form-label" for="modal-data-referencia">Data da captação</label>
                        <input type="date"
                               name="data_referencia"
                               id="modal-data-referencia"
                               class="form-control"
                               value="{{ old('data_referencia', $dataReferencia ?? now()->toDateString()) }}"
                               required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="modal-id-captacao-carteira">Carteira</label>
                        <select name="id_captacao_carteira"
                                id="modal-id-captacao-carteira"
                                class="form-select"
                                data-search-select
                                data-search-select-modal="#modal-criar-captacao"
                                data-placeholder="Selecione ou pesquise a carteira"
                                required>
                            <option value="">Selecione a carteira…</option>
                            @foreach ($carteiras as $carteira)
                                <option value="{{ $carteira->id }}" @selected((int) old('id_captacao_carteira') === $carteira->id)>
                                    {{ $carteira->nome }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="ri-add-line me-1"></i>
                        Criar Captação
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modalEl = document.getElementById('modal-criar-captacao');

                if (!modalEl || !modalEl.dataset.reopenOnError) {
                    return;
                }

                window.bootstrap?.Modal.getOrCreateInstance(modalEl).show();
            });

            document.getElementById('modal-criar-captacao')?.addEventListener('shown.bs.modal', function (event) {
                const modal = event.target;

                if (!window.AdminSearchSelect || !window.jQuery) {
                    return;
                }

                modal.querySelectorAll('[data-search-select]').forEach(function (selectEl) {
                    const $select = window.jQuery(selectEl);

                    if ($select.hasClass('select2-hidden-accessible')) {
                        return;
                    }

                    const dropdownParent = selectEl.dataset.searchSelectModal
                        ? window.jQuery(selectEl.dataset.searchSelectModal)
                        : window.jQuery(modal);

                    $select.select2({
                        allowClear: !selectEl.required,
                        dropdownParent,
                        language: {
                            noResults: () => 'Nenhum resultado encontrado',
                            searching: () => 'Pesquisando…',
                        },
                        placeholder: selectEl.dataset.placeholder || 'Selecione…',
                        width: '100%',
                    });
                });
            });
        </script>
    @endpush
@endonce
