<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Grupos</title>
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
    <h1>Relatório de Grupos</h1>
    <p class="meta">Gerado em: {{ $geradoEm->format('d/m/Y H:i:s') }}</p>
    <p class="meta">Gerado por: {{ $geradoPor }}</p>
    <p class="meta">Total: {{ $grupos->count() }} grupo(s) | Limite por PDF: {{ $limiteRegistros }}</p>
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
                <th width="70%">Nome</th>
                <th width="30%">Criado em</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($grupos as $grupo)
                <tr>
                    <td>{{ $grupo->nome }}</td>
                    <td>{{ optional($grupo->created_at)->format('d/m/Y H:i') ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2" class="empty">Nenhum grupo corresponde aos filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
