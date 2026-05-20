@php
    use App\Enums\FrutaUmIcms;
    /** @var \Illuminate\Support\Collection<int, \App\Models\Estado>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Estado> $estados */
    /** @var array<int, array<string, string>> $icmsForm */
@endphp

<div class="card mt-3">
    <div class="card-header">
        <h5 class="header-title mb-0">ICMS por estado</h5>
        <p class="text-muted mb-0 small">
            Ceará: compra em R$/KG ou R$/UM. Pernambuco: venda em % (PCT), dentro e fora do estado.
            Use a <a href="{{ route('admin.frutas.icms.index') }}">tela de ICMS</a> para carga em lote.
        </p>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="bg-light bg-opacity-50">
                    <tr>
                        <th rowspan="2" class="align-middle">Estado</th>
                        <th colspan="2" class="text-center">Compra nacional</th>
                        <th colspan="2" class="text-center">Compra exterior</th>
                        <th colspan="2" class="text-center">Venda fora do estado</th>
                        <th colspan="2" class="text-center">Venda dentro do estado</th>
                    </tr>
                    <tr>
                        <th>Valor</th><th>UM</th>
                        <th>Valor</th><th>UM</th>
                        <th>Valor</th><th>UM</th>
                        <th>Valor</th><th>UM</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($estados as $estado)
                        @php($linha = $icmsForm[$estado->id] ?? [])
                        <tr>
                            <td>
                                <span class="fw-semibold">{{ $estado->nome }}</span>
                                <span class="badge bg-light text-muted ms-1">{{ $estado->abreviacao }}</span>
                            </td>
                            <td>
                                <input type="text" inputmode="decimal"
                                       name="icms[{{ $estado->id }}][entrada_nacional]"
                                       value="{{ old('icms.'.$estado->id.'.entrada_nacional', $linha['entrada_nacional'] ?? '0.00') }}"
                                       class="form-control form-control-sm">
                            </td>
                            <td>
                                <select name="icms[{{ $estado->id }}][entrada_um_nacional]" class="form-select form-select-sm">
                                    @foreach (FrutaUmIcms::valoresEntrada() as $umValor)
                                        <option value="{{ $umValor }}" @selected(old('icms.'.$estado->id.'.entrada_um_nacional', $linha['entrada_um_nacional'] ?? FrutaUmIcms::KG->value) === $umValor)>{{ $umValor }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="text" inputmode="decimal"
                                       name="icms[{{ $estado->id }}][entrada_externo]"
                                       value="{{ old('icms.'.$estado->id.'.entrada_externo', $linha['entrada_externo'] ?? '0.00') }}"
                                       class="form-control form-control-sm">
                            </td>
                            <td>
                                <select name="icms[{{ $estado->id }}][entrada_um_externo]" class="form-select form-select-sm">
                                    @foreach (FrutaUmIcms::valoresEntrada() as $umValor)
                                        <option value="{{ $umValor }}" @selected(old('icms.'.$estado->id.'.entrada_um_externo', $linha['entrada_um_externo'] ?? FrutaUmIcms::KG->value) === $umValor)>{{ $umValor }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="text" inputmode="decimal"
                                       name="icms[{{ $estado->id }}][saida_importada]"
                                       value="{{ old('icms.'.$estado->id.'.saida_importada', $linha['saida_importada'] ?? '0.00') }}"
                                       class="form-control form-control-sm">
                            </td>
                            <td>
                                <select name="icms[{{ $estado->id }}][saida_um_importada]" class="form-select form-select-sm">
                                    @foreach (FrutaUmIcms::valoresSaida() as $umValor)
                                        <option value="{{ $umValor }}" @selected(old('icms.'.$estado->id.'.saida_um_importada', $linha['saida_um_importada'] ?? FrutaUmIcms::PCT->value) === $umValor)>{{ $umValor }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input type="text" inputmode="decimal"
                                       name="icms[{{ $estado->id }}][saida_nacional]"
                                       value="{{ old('icms.'.$estado->id.'.saida_nacional', $linha['saida_nacional'] ?? '0.00') }}"
                                       class="form-control form-control-sm">
                            </td>
                            <td>
                                <select name="icms[{{ $estado->id }}][saida_um_nacional]" class="form-select form-select-sm">
                                    @foreach (FrutaUmIcms::valoresSaida() as $umValor)
                                        <option value="{{ $umValor }}" @selected(old('icms.'.$estado->id.'.saida_um_nacional', $linha['saida_um_nacional'] ?? FrutaUmIcms::PCT->value) === $umValor)>{{ $umValor }}</option>
                                    @endforeach
                                </select>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
