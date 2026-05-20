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
                    <th>Entr. nac. kg</th>
                    <th>Entr. intl. kg</th>
                    <th>V. nac. dentro %</th>
                    <th>V. nac. fora %</th>
                    <th>V. intl. dentro %</th>
                    <th>V. intl. fora %</th>
                    <th>Vigente</th>
                </tr>
            </thead>
            <tbody>
                @foreach($historicos as $historico)
                    @php($a = $historico->aliquotasArray())
                    <tr>
                        <td>{{ $historico->created_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $historico->origem }}</td>
                        <td>{{ number_format((float) $a['entrada_nacional_kg'], 2, ',', '.') }}</td>
                        <td>{{ number_format((float) $a['entrada_internacional_kg'], 2, ',', '.') }}</td>
                        <td>{{ number_format((float) $a['saida_nacional_dentro_pct'], 2, ',', '.') }}</td>
                        <td>{{ number_format((float) $a['saida_nacional_fora_pct'], 2, ',', '.') }}</td>
                        <td>{{ number_format((float) $a['saida_internacional_dentro_pct'], 2, ',', '.') }}</td>
                        <td>{{ number_format((float) $a['saida_internacional_fora_pct'], 2, ',', '.') }}</td>
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
