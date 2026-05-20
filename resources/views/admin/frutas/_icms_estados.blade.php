@php
    use App\Support\Frutas\FrutaIcmsLinhaFormulario;
    /** @var \Illuminate\Support\Collection<int, \App\Models\Estado>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Estado> $estados */
    /** @var array<int, array<string, string>> $icmsForm */
@endphp

<div class="card mt-3">
    <div class="card-header">
        <h5 class="header-title mb-0">ICMS por estado</h5>
        <p class="text-muted mb-0 small">
            Entrada em R$/kg (nacional e internacional). Venda em % com quatro combinações (nacional/internacional × dentro/fora do estado).
            <a href="{{ route('admin.frutas.icms.index') }}">Tela de ICMS</a> para carga em lote.
        </p>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="bg-light bg-opacity-50">
                    <tr>
                        <th rowspan="2" class="align-middle">Estado</th>
                        <th colspan="2" class="text-center">Entrada (R$/kg)</th>
                        <th colspan="4" class="text-center">Venda (%)</th>
                    </tr>
                    <tr>
                        <th>Nacional</th>
                        <th>Internacional</th>
                        <th>Nac. dentro</th>
                        <th>Nac. fora</th>
                        <th>Intl. dentro</th>
                        <th>Intl. fora</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($estados as $estado)
                        @php($linha = $icmsForm[$estado->id] ?? FrutaIcmsLinhaFormulario::vazia())
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $estado->nome }}</span>
                                <span class="badge bg-light text-muted ms-1">{{ $estado->abreviacao }}</span>
                            </td>
                            @foreach (FrutaIcmsLinhaFormulario::chaves() as $chave)
                                <td>
                                    <input type="text" inputmode="decimal"
                                           name="icms[{{ $estado->id }}][{{ $chave }}]"
                                           value="{{ old('icms.'.$estado->id.'.'.$chave, $linha[$chave] ?? '0.00') }}"
                                           class="form-control form-control-sm">
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
