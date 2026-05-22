<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Frutas</title>
    <style>
        @page { margin: 12mm 8mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 8px; color: #111; margin: 0; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        p { margin: 0 0 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #bbb; padding: 3px; vertical-align: top; }
        th { background: #eee; text-align: left; font-weight: bold; }
        .meta { font-size: 8px; color: #333; }
        .empty { text-align: center; padding: 12px; }
    </style>
</head>
<body>
    <h1>Relatório de Frutas</h1>
    <p class="meta">Gerado em: {{ $geradoEm->format('d/m/Y H:i:s') }}</p>
    <p class="meta">Gerado por: {{ $geradoPor }}</p>
    <p class="meta">Total: {{ $frutas->count() }} fruta(s) | Limite por PDF: {{ $limiteRegistros }}</p>
    <p class="meta">
        Filtros:
        @if (($filtros['search'] ?? '') !== '')
            pesquisa "{{ $filtros['search'] }}"
        @else
            nenhum
        @endif
    </p>

    <table>
        <thead>
            <tr>
                <th width="8%">ID CIGAM</th>
                <th width="18%">Nome</th>
                <th width="8%">Proced.</th>
                <th width="8%">Unidade</th>
                <th width="7%">Kg/un.</th>
                <th width="8%">Ent. nac. (R$/kg)</th>
                <th width="8%">Ent. intl. (R$/kg)</th>
                <th width="7%">V. nac. dentro (%)</th>
                <th width="7%">V. nac. fora (%)</th>
                <th width="7%">V. intl. dentro (%)</th>
                <th width="7%">V. intl. fora (%)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($frutas as $fruta)
                @php
                    $icmsCe = app(\App\Services\Frutas\FrutaIcmsAliquotaResolver::class)->mapaParaFormulario($fruta, \App\Models\Estado::ID_CEARA);
                @endphp
                <tr>
                    <td>{{ $fruta->id_cigam }}</td>
                    <td>{{ $fruta->nome }}</td>
                    <td>{{ $fruta->procedencia ?? 'NACIONAL' }}</td>
                    <td>{{ $fruta->unidade_medicao }}</td>
                    @php
                        $casasKgPdf = \App\Enums\FrutaUnidadeMedicao::tryFrom((string) $fruta->unidade_medicao)?->casasDecimaisKg() ?? 2;
                    @endphp
                    <td>{{ number_format((float) $fruta->kg_por_unidade_medicao, $casasKgPdf, ',', '.') }}</td>
                    <td>{{ number_format((float) ($icmsCe['entrada_nacional_kg'] ?? 0), 2, ',', '.') }}</td>
                    <td>{{ number_format((float) ($icmsCe['entrada_internacional_kg'] ?? 0), 2, ',', '.') }}</td>
                    <td>{{ number_format((float) ($icmsCe['saida_nacional_dentro_pct'] ?? 0), 2, ',', '.') }}</td>
                    <td>{{ number_format((float) ($icmsCe['saida_nacional_fora_pct'] ?? 0), 2, ',', '.') }}</td>
                    <td>{{ number_format((float) ($icmsCe['saida_internacional_dentro_pct'] ?? 0), 2, ',', '.') }}</td>
                    <td>{{ number_format((float) ($icmsCe['saida_internacional_fora_pct'] ?? 0), 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="empty">Nenhuma fruta corresponde aos filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
