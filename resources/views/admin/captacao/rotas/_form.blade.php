@php
    /** @var \App\Models\Captacao\CaptacaoRota $rota */
    $rota = $rota ?? new \App\Models\Captacao\CaptacaoRota(['ativo' => true]);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="id_unidade_negocio_galpao">Galpão</label>
        <select name="id_unidade_negocio_galpao"
                id="id_unidade_negocio_galpao"
                class="form-select @error('id_unidade_negocio_galpao') is-invalid @enderror"
                required>
            <option value="">Selecione…</option>
            @foreach ($galpoes as $galpao)
                <option value="{{ $galpao->id }}"
                        @selected((int) old('id_unidade_negocio_galpao', $rota->id_unidade_negocio_galpao ?? $galpaoId) === $galpao->id)>
                    {{ $galpao->nome }}
                </option>
            @endforeach
        </select>
        @error('id_unidade_negocio_galpao')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="nome">Nome da rota</label>
        <input type="text"
               name="nome"
               id="nome"
               class="form-control @error('nome') is-invalid @enderror"
               value="{{ old('nome', $rota->nome) }}"
               maxlength="120"
               required
               placeholder="Ex.: Rota 1 — Centro">
        @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label class="form-label" for="id_veiculo">Veículo (opcional)</label>
        <select name="id_veiculo" id="id_veiculo" class="form-select @error('id_veiculo') is-invalid @enderror">
            <option value="">Sem veículo vinculado</option>
            @foreach ($veiculos as $veiculo)
                <option value="{{ $veiculo->id }}" @selected((int) old('id_veiculo', $rota->id_veiculo) === $veiculo->id)>
                    {{ $veiculo->nome }} (SBS {{ $veiculo->id_sbs }})
                </option>
            @endforeach
        </select>
        @error('id_veiculo')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 d-flex align-items-end">
        <div class="form-check mb-2">
            <input type="hidden" name="ativo" value="0">
            <input type="checkbox"
                   class="form-check-input"
                   name="ativo"
                   id="ativo"
                   value="1"
                   @checked(old('ativo', $rota->ativo ?? true))>
            <label class="form-check-label" for="ativo">Rota ativa</label>
        </div>
    </div>
</div>
