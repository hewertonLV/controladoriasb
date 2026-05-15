<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Estoques</title>
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
    <h1>Relatório de Estoques consolidados</h1>
    <p class="meta">Gerado em: {{ $geradoEm->format('d/m/Y H:i:s') }}</p>
    <p class="meta">Gerado por: {{ $geradoPor }}</p>
    <p class="meta">Total: {{ $estoques->count() }} posição(ões) | Limite por PDF: {{ $limiteRegistros }}</p>

    <table>
        <thead>
            <tr>
                <th>Unidade</th>
                <th>Fruta</th>
                <th>Qtd kg</th>
                <th>Qtd UM</th>
                <th>Pço médio kg</th>
                <th>Pço médio UM</th>
                <th>Valor total</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($estoques as $e)
                <tr>
                    <td>{{ $e->unidadeNegocio->nome ?? '' }} ({{ $e->unidadeNegocio->id_cigam ?? '' }})</td>
                    <td>{{ $e->fruta->nome ?? '' }} ({{ $e->fruta->id_cigam ?? '' }})</td>
                    <td>{{ number_format((float) $e->qtd_fruta_kg, 2, ',', '.') }}</td>
                    <td>{{ number_format((float) $e->qtd_fruta_um, 2, ',', '.') }} {{ $e->fruta->unidade_medicao ?? '' }}</td>
                    <td>{{ number_format((float) $e->preco_medio_kg, 2, ',', '.') }}</td>
                    <td>{{ number_format((float) $e->preco_medio_um, 2, ',', '.') }}</td>
                    <td>{{ number_format((float) $e->valor_total_acumulado, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="empty">Nenhuma posição encontrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
