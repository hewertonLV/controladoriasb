@php
    use App\Enums\FrutaUmIcms;
    /** @var array<string, string> $icmsLinha */
    $editando = isset($fruta) && isset($estado);
@endphp

<div class="row g-3">
    @if (! $editando)
        <div class="col-md-6">
            <label for="fruta_id" class="form-label">Fruta <span class="text-danger">*</span></label>
            <select name="fruta_id" id="fruta_id" class="form-select @error('fruta_id') is-invalid @enderror" required>
                <option value="">Selecione...</option>
                @foreach ($frutas as $f)
                    <option value="{{ $f->id }}" @selected((int) old('fruta_id') === $f->id)>
                        {{ $f->id_cigam }} — {{ $f->nome }}
                    </option>
                @endforeach
            </select>
            @error('fruta_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
            <label for="id_estado" class="form-label">Estado <span class="text-danger">*</span></label>
            <select name="id_estado" id="id_estado" class="form-select @error('id_estado') is-invalid @enderror" required>
                <option value="">Selecione...</option>
                @foreach ($estados as $e)
                    <option value="{{ $e->id }}" @selected((int) old('id_estado') === $e->id)>
                        {{ $e->nome }} ({{ $e->abreviacao }})
                    </option>
                @endforeach
            </select>
            @error('id_estado')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    @else
        <div class="col-12">
            <p class="mb-0">
                <span class="text-muted">Fruta:</span>
                <span class="fw-semibold">{{ $fruta->nome }}</span>
                <code class="ms-1">{{ $fruta->id_cigam }}</code>
                <span class="text-muted ms-3">Estado:</span>
                <span class="fw-semibold">{{ $estado->nome }}</span>
                <span class="badge bg-light text-muted">{{ $estado->abreviacao }}</span>
            </p>
        </div>
    @endif
</div>

<div class="card mt-3">
    <div class="card-header">
        <h5 class="header-title mb-0">Valores de ICMS</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6 col-lg-3">
                <label class="form-label">Compra nacional</label>
                <input type="text" inputmode="decimal" name="entrada_nacional"
                       value="{{ old('entrada_nacional', $icmsLinha['entrada_nacional'] ?? '0.00') }}"
                       class="form-control @error('entrada_nacional') is-invalid @enderror">
                @error('entrada_nacional')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label">UM</label>
                <select name="entrada_um_nacional" class="form-select">
                    @foreach (FrutaUmIcms::valoresEntrada() as $umValor)
                        <option value="{{ $umValor }}" @selected(old('entrada_um_nacional', $icmsLinha['entrada_um_nacional'] ?? FrutaUmIcms::KG->value) === $umValor)>{{ $umValor }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label">Compra exterior</label>
                <input type="text" inputmode="decimal" name="entrada_externo"
                       value="{{ old('entrada_externo', $icmsLinha['entrada_externo'] ?? '0.00') }}"
                       class="form-control @error('entrada_externo') is-invalid @enderror">
                @error('entrada_externo')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label">UM</label>
                <select name="entrada_um_externo" class="form-select">
                    @foreach (FrutaUmIcms::valoresEntrada() as $umValor)
                        <option value="{{ $umValor }}" @selected(old('entrada_um_externo', $icmsLinha['entrada_um_externo'] ?? FrutaUmIcms::KG->value) === $umValor)>{{ $umValor }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label">Venda fora do estado (%)</label>
                <input type="text" inputmode="decimal" name="saida_importada"
                       value="{{ old('saida_importada', $icmsLinha['saida_importada'] ?? '0.00') }}"
                       class="form-control @error('saida_importada') is-invalid @enderror">
                @error('saida_importada')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label">UM</label>
                <select name="saida_um_importada" class="form-select">
                    @foreach (FrutaUmIcms::valoresSaida() as $umValor)
                        <option value="{{ $umValor }}" @selected(old('saida_um_importada', $icmsLinha['saida_um_importada'] ?? FrutaUmIcms::PCT->value) === $umValor)>{{ $umValor }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label">Venda dentro do estado (%)</label>
                <input type="text" inputmode="decimal" name="saida_nacional"
                       value="{{ old('saida_nacional', $icmsLinha['saida_nacional'] ?? '0.00') }}"
                       class="form-control @error('saida_nacional') is-invalid @enderror">
                @error('saida_nacional')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6 col-lg-3">
                <label class="form-label">UM</label>
                <select name="saida_um_nacional" class="form-select">
                    @foreach (FrutaUmIcms::valoresSaida() as $umValor)
                        <option value="{{ $umValor }}" @selected(old('saida_um_nacional', $icmsLinha['saida_um_nacional'] ?? FrutaUmIcms::PCT->value) === $umValor)>{{ $umValor }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>
