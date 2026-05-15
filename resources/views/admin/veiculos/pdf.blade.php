<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Veículos</title>
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
    <h1>Relatório de Veículos</h1>
    <p class="meta">Gerado em: {{ $geradoEm->format('d/m/Y H:i:s') }}</p>
    <p class="meta">Gerado por: {{ $geradoPor }}</p>
    <p class="meta">Total: {{ $veiculos->count() }} veículo(s) | Limite por PDF: {{ $limiteRegistros }}</p>
    <p class="meta">
        Filtros:
        @if ($filtros['search'] !== '')
            pesquisa "{{ $filtros['search'] }}"
        @endif
        @if ($filtros['status'] !== null)
            {{ $filtros['search'] !== '' ? ' | ' : '' }} status {{ $filtros['status'] === 'ATIVO' ? 'ativos' : 'inativos' }}
        @endif
        @if ($filtros['search'] === '' && $filtros['status'] === null)
            nenhum
        @endif
    </p>

    <table>
        <thead>
            <tr>
                <th width="12%">ID SBS</th>
                <th width="30%">Nome</th>
                <th width="18%">Tipo</th>
                <th width="25%">Unidade de negócio</th>
                <th width="15%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($veiculos as $veiculo)
                <tr>
                    <td>{{ $veiculo->id_sbs }}</td>
                    <td>{{ $veiculo->nome }}</td>
                    <td>{{ $veiculo->tipo }}</td>
                    <td>{{ $veiculo->unidadeNegocio?->nome ?? '—' }}</td>
                    <td>{{ $veiculo->status === 'ATIVO' ? 'Ativo' : 'Inativo' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="empty">Nenhum veículo corresponde aos filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
