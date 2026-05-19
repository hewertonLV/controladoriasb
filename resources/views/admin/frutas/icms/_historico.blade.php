@if($historicos->isNotEmpty())
    <hr class="my-4">
    <h5 class="mb-3">Histórico de alterações (ICMS)</h5>
    <p class="text-muted small mb-3">
        Versões registradas para recálculo e auditoria. Movimentações já gravadas usam o snapshot na própria linha.
    </p>
    <div class="table-responsive">
        <table class="table table-sm table-striped align-middle mb-0">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Origem</th>
                    <th>Entrada nacional</th>
                    <th>Entrada exterior</th>
                    <th>Saída importada</th>
                    <th>Saída nacional</th>
                    <th>Vigente</th>
                </tr>
            </thead>
            <tbody>
                @foreach($historicos as $historico)
                    <tr>
                        <td>{{ $historico->created_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $historico->origem }}</td>
                        <td>{{ number_format((float) $historico->entrada_nacional, 2, ',', '.') }} {{ $historico->um_icms_nacional }}</td>
                        <td>{{ number_format((float) $historico->entrada_externo, 2, ',', '.') }} {{ $historico->um_icms_externo }}</td>
                        <td>{{ number_format((float) $historico->saida_importada, 2, ',', '.') }} {{ $historico->um_icms_venda_importada }}</td>
                        <td>{{ number_format((float) $historico->saida_nacional, 2, ',', '.') }} {{ $historico->um_icms_venda_nacional }}</td>
                        <td>
                            @if($historico->status_position)
                                <span class="badge bg-success-subtle text-success">Sim</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">Não</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

