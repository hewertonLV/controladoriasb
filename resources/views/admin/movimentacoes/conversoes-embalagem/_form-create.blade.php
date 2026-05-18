@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Empresa> $empresas_origem */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Fruta> $frutas_origem */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Fruta> $frutas_destino */
@endphp

<form method="POST" action="{{ route('admin.movimentacoes.conversoes-embalagem.store') }}" class="row g-3">
    @csrf

    <div class="col-md-6">
        <label for="id_empresa_origem" class="form-label">Unidade de origem <span class="text-danger">*</span></label>
        <select name="id_empresa_origem" id="id_empresa_origem" class="form-select @error('id_empresa_origem') is-invalid @enderror" required>
            <option value="">Selecione…</option>
            @foreach ($empresas_origem as $empresa)
                <option value="{{ $empresa->id }}" @selected((string) old('id_empresa_origem') === (string) $empresa->id)>
                    {{ $empresa->nomeExibicao() }}
                </option>
            @endforeach
        </select>
        @error('id_empresa_origem')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label for="id_fruta_origem" class="form-label">Fruta que possuo <span class="text-danger">*</span></label>
        <select name="id_fruta_origem" id="id_fruta_origem" class="form-select @error('id_fruta_origem') is-invalid @enderror" required>
            <option value="">Selecione…</option>
            @foreach ($frutas_origem as $fruta)
                <option
                    value="{{ $fruta->id }}"
                    data-estoque-origens="{{ implode(',', $fruta->estoque_origem_empresa_ids ?? []) }}"
                    data-kg-por-um="{{ number_format((float) $fruta->kg_por_unidade_medicao, 2, '.', '') }}"
                    @selected((string) old('id_fruta_origem') === (string) $fruta->id)
                >
                    {{ $fruta->nome }} — {{ $fruta->kg_por_unidade_medicao }} kg/UM
                </option>
            @endforeach
        </select>
        @error('id_fruta_origem')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <small class="text-muted">Só aparecem frutas com saldo na unidade escolhida.</small>
    </div>

    <div class="col-md-4">
        <label for="qtd_fruta_um" class="form-label">Qtd original (UM) <span class="text-danger">*</span></label>
        <input type="text" name="qtd_fruta_um" id="qtd_fruta_um" value="{{ old('qtd_fruta_um') }}" data-mask-decimal-br class="form-control @error('qtd_fruta_um') is-invalid @enderror" inputmode="decimal" required>
        @error('qtd_fruta_um')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Peso original calculado</label>
        <input type="text" id="peso_original_calculado" class="form-control" readonly value="0,00 kg">
    </div>

    <div class="col-md-4">
        <label for="id_fruta_destino" class="form-label">Converter para <span class="text-danger">*</span></label>
        <select name="id_fruta_destino" id="id_fruta_destino" class="form-select @error('id_fruta_destino') is-invalid @enderror" required>
            <option value="">Selecione…</option>
            @foreach ($frutas_destino as $fruta)
                <option
                    value="{{ $fruta->id }}"
                    data-kg-por-um="{{ number_format((float) $fruta->kg_por_unidade_medicao, 2, '.', '') }}"
                    @selected((string) old('id_fruta_destino') === (string) $fruta->id)
                >
                    {{ $fruta->nome }} — {{ $fruta->kg_por_unidade_medicao }} kg/UM
                </option>
            @endforeach
        </select>
        @error('id_fruta_destino')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label for="qtd_resultante_um" class="form-label">Qtd resultante (UM) <span class="text-danger">*</span></label>
        <input type="text" name="qtd_resultante_um" id="qtd_resultante_um" value="{{ old('qtd_resultante_um') }}" data-mask-decimal-br class="form-control @error('qtd_resultante_um') is-invalid @enderror" inputmode="decimal" required>
        @error('qtd_resultante_um')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">Peso resultante calculado</label>
        <input type="text" id="peso_resultante_calculado" class="form-control" readonly value="0,00 kg">
    </div>

    <div class="col-md-4">
        <label class="form-label">Perda em UM da origem</label>
        <input type="text" id="perda_um_calculada" class="form-control" readonly value="0,00 UM">
        <small class="text-muted">Ex.: 10 cx para 9 cx registra perda de 1 cx da fruta original.</small>
    </div>

    <div class="col-md-4">
        <label class="form-label">Perda em peso</label>
        <input type="text" id="perda_calculada" class="form-control" readonly value="0,00 kg">
        <small class="text-muted">A perda econômica usa o preço médio da fruta original.</small>
    </div>

    <div class="col-12">
        <label for="observacao" class="form-label">Observação</label>
        <textarea name="observacao" id="observacao" rows="3" class="form-control @error('observacao') is-invalid @enderror">{{ old('observacao') }}</textarea>
        @error('observacao')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">Registrar conversão</button>
    </div>
</form>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const origem = document.getElementById('id_empresa_origem');
        const frutaOrigem = document.getElementById('id_fruta_origem');
        const frutaDestino = document.getElementById('id_fruta_destino');
        const qtdOrigem = document.getElementById('qtd_fruta_um');
        const qtdResultante = document.getElementById('qtd_resultante_um');
        const pesoOriginal = document.getElementById('peso_original_calculado');
        const pesoResultante = document.getElementById('peso_resultante_calculado');
        const perdaUm = document.getElementById('perda_um_calculada');
        const perda = document.getElementById('perda_calculada');

        const parseDecimal = (value) => Number(String(value || '0').replace(/\./g, '').replace(',', '.')) || 0;
        const formatKg = (value) => `${value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} kg`;

        const filtrarFrutaOrigem = () => {
            const origemId = origem.value;

            frutaOrigem.querySelectorAll('option').forEach((option) => {
                if (!option.value) return;

                const permitido = origemId !== '' && (option.dataset.estoqueOrigens || '').split(',').includes(origemId);
                option.hidden = !permitido;
                option.disabled = !permitido;
            });

            if (frutaOrigem.selectedOptions.length && frutaOrigem.selectedOptions[0].disabled) {
                frutaOrigem.value = '';
            }
        };

        const recalcular = () => {
            const kgOrigem = Number(frutaOrigem.selectedOptions[0]?.dataset?.kgPorUm || 0);
            const kgDestino = Number(frutaDestino.selectedOptions[0]?.dataset?.kgPorUm || 0);
            const qtdOriginalUm = parseDecimal(qtdOrigem.value);
            const qtdResultanteUm = parseDecimal(qtdResultante.value);
            const original = qtdOriginalUm * kgOrigem;
            const resultante = qtdResultanteUm * kgDestino;
            const perdaOriginalUm = Math.max(qtdOriginalUm - qtdResultanteUm, 0);
            const perdaKg = Math.max(original - resultante, 0);

            pesoOriginal.value = formatKg(original);
            pesoResultante.value = formatKg(resultante);
            perdaUm.value = `${perdaOriginalUm.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} UM`;
            perda.value = formatKg(perdaKg);
        };

        origem.addEventListener('change', () => {
            filtrarFrutaOrigem();
            recalcular();
        });
        [frutaOrigem, frutaDestino, qtdOrigem, qtdResultante].forEach((el) => el.addEventListener('change', recalcular));
        [qtdOrigem, qtdResultante].forEach((el) => el.addEventListener('input', recalcular));

        filtrarFrutaOrigem();
        recalcular();
    });
</script>
