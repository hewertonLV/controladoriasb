@php
    $carteira = $carteira ?? new \App\Models\Captacao\CaptacaoCarteira(['ativo' => true]);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="nome">Nome da carteira</label>
        <input type="text" name="nome" id="nome" class="form-control @error('nome') is-invalid @enderror"
               value="{{ old('nome', $carteira->nome) }}" maxlength="120" required>
        @error('nome')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label" for="id_unidade_negocio_faturamento">Faturamento</label>
        <select name="id_unidade_negocio_faturamento" id="id_unidade_negocio_faturamento" class="form-select @error('id_unidade_negocio_faturamento') is-invalid @enderror" required>
            <option value="">Selecione…</option>
            @foreach ($faturamentos as $un)
                <option value="{{ $un->id }}" @selected((int) old('id_unidade_negocio_faturamento', $carteira->id_unidade_negocio_faturamento) === $un->id)>{{ $un->nome }}</option>
            @endforeach
        </select>
        @error('id_unidade_negocio_faturamento')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label" for="id_unidade_negocio_galpao">Galpão / estoque físico</label>
        <select name="id_unidade_negocio_galpao" id="id_unidade_negocio_galpao" class="form-select @error('id_unidade_negocio_galpao') is-invalid @enderror" required>
            <option value="">Selecione…</option>
            @foreach ($galpoes as $un)
                <option value="{{ $un->id }}" @selected((int) old('id_unidade_negocio_galpao', $carteira->id_unidade_negocio_galpao) === $un->id)>{{ $un->nome }}</option>
            @endforeach
        </select>
        @error('id_unidade_negocio_galpao')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>
