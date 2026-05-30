@php
    /** @var \App\Models\Movimentacoes\TransferenciaDemanda|null $demanda */
    $linhas = old('linhas', $demanda?->linhas->map(fn ($l) => ['id_fruta' => $l->id_fruta, 'qtd_um' => $l->qtd_um])->all() ?? [['id_fruta' => '', 'qtd_um' => '']]);
@endphp

<div id="demanda-linhas-wrap">
    @foreach ($linhas as $idx => $linha)
        <div class="row g-2 align-items-end mb-2 demanda-linha-row">
            <div class="col-md-6">
                <label class="form-label small mb-0">Fruta</label>
                <select name="linhas[{{ $idx }}][id_fruta]" class="form-select form-select-sm" required>
                    <option value="">Selecione</option>
                    @foreach ($frutas as $fruta)
                        <option value="{{ $fruta->id }}" @selected((string) ($linha['id_fruta'] ?? '') === (string) $fruta->id)>
                            {{ $fruta->nome }} ({{ $fruta->unidade_medicao }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small mb-0">Quantidade</label>
                <input type="number" step="0.001" min="0.001" name="linhas[{{ $idx }}][qtd_um]" class="form-control form-control-sm" value="{{ $linha['qtd_um'] ?? '' }}" required>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-soft-danger btn-sm w-100 btn-remover-linha-demanda" @if ($idx === 0 && count($linhas) === 1) disabled @endif>&times;</button>
            </div>
        </div>
    @endforeach
</div>
<button type="button" class="btn btn-soft-secondary btn-sm" id="btn-adicionar-linha-demanda">
    <i class="ri-add-line"></i> Adicionar fruta
</button>

@push('scripts')
<script>
(function () {
    const wrap = document.getElementById('demanda-linhas-wrap');
    const btnAdd = document.getElementById('btn-adicionar-linha-demanda');
    if (!wrap || !btnAdd) return;

    btnAdd.addEventListener('click', function () {
        const idx = wrap.querySelectorAll('.demanda-linha-row').length;
        const firstSelect = wrap.querySelector('select[name^="linhas"]');
        if (!firstSelect) return;
        const clone = firstSelect.closest('.demanda-linha-row').cloneNode(true);
        clone.querySelectorAll('select, input').forEach((el) => {
            const name = el.getAttribute('name').replace(/\[\d+\]/, `[${idx}]`);
            el.setAttribute('name', name);
            if (el.tagName === 'SELECT') el.selectedIndex = 0;
            if (el.tagName === 'INPUT') el.value = '';
        });
        const btnRem = clone.querySelector('.btn-remover-linha-demanda');
        if (btnRem) btnRem.disabled = false;
        wrap.appendChild(clone);
    });

    wrap.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-remover-linha-demanda');
        if (!btn || btn.disabled) return;
        const rows = wrap.querySelectorAll('.demanda-linha-row');
        if (rows.length <= 1) return;
        btn.closest('.demanda-linha-row')?.remove();
    });
})();
</script>
@endpush
