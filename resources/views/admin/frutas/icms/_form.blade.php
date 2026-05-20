@php
    use App\Support\Frutas\FrutaIcmsLinhaFormulario;
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
                <span class="badge bg-light text-muted ms-1">{{ $fruta->procedencia }}</span>
                <span class="text-muted ms-3">Estado:</span>
                <span class="fw-semibold">{{ $estado->nome }}</span>
                <span class="badge bg-light text-muted">{{ $estado->abreviacao }}</span>
            </p>
        </div>
    @endif
</div>

<div class="card mt-3">
    <div class="card-header">
        <h5 class="header-title mb-0">Alíquotas</h5>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12"><h6 class="text-muted mb-0">Entrada (R$/kg)</h6></div>
            <div class="col-md-6">
                <label class="form-label">Nacional</label>
                <input type="text" inputmode="decimal" name="{{ FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG }}"
                       value="{{ old(FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG, $icmsLinha[FrutaIcmsLinhaFormulario::ENTRADA_NACIONAL_KG] ?? '0.00') }}"
                       class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Internacional</label>
                <input type="text" inputmode="decimal" name="{{ FrutaIcmsLinhaFormulario::ENTRADA_INTERNACIONAL_KG }}"
                       value="{{ old(FrutaIcmsLinhaFormulario::ENTRADA_INTERNACIONAL_KG, $icmsLinha[FrutaIcmsLinhaFormulario::ENTRADA_INTERNACIONAL_KG] ?? '0.00') }}"
                       class="form-control">
            </div>
            <div class="col-12 mt-2"><h6 class="text-muted mb-0">Venda (%)</h6></div>
            @foreach ([
                FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_DENTRO_PCT => 'Nacional — dentro do estado',
                FrutaIcmsLinhaFormulario::SAIDA_NACIONAL_FORA_PCT => 'Nacional — fora do estado',
                FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_DENTRO_PCT => 'Internacional — dentro do estado',
                FrutaIcmsLinhaFormulario::SAIDA_INTERNACIONAL_FORA_PCT => 'Internacional — fora do estado',
            ] as $chave => $rotulo)
                <div class="col-md-6">
                    <label class="form-label">{{ $rotulo }}</label>
                    <input type="text" inputmode="decimal" name="{{ $chave }}"
                           value="{{ old($chave, $icmsLinha[$chave] ?? '0.00') }}"
                           class="form-control">
                </div>
            @endforeach
        </div>
    </div>
</div>
