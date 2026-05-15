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
                <th width="22%">Nome</th>
                <th width="10%">Unidade</th>
                <th width="8%">Kg/un.</th>
                <th width="10%">ICMS ex. compra</th>
                <th width="10%">ICMS na compra</th>
                <th width="6%">UM ICMS</th>
                <th width="8%">ICMS venda %</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($frutas as $fruta)
                <tr>
                    <td>{{ $fruta->id_cigam }}</td>
                    <td>{{ $fruta->nome }}</td>
                    <td>{{ $fruta->unidade_medicao }}</td>
                    <td>{{ number_format((float) $fruta->kg_por_unidade_medicao, 2, ',', '.') }}</td>
                    <td>{{ number_format((float) $fruta->icms_ex_compra, 2, ',', '.') }}</td>
                    <td>{{ number_format((float) $fruta->icms_na_compra, 2, ',', '.') }}</td>
                    <td>{{ $fruta->um_icms }}</td>
                    <td>{{ number_format((float) $fruta->icms_venda, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="empty">Nenhuma fruta corresponde aos filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
