<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Fretes</title>
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
    @php
        $fmtMoeda = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    @endphp

    <h1>Relatório de Fretes</h1>
    <p class="meta">Gerado em: {{ $geradoEm->format('d/m/Y H:i:s') }}</p>
    <p class="meta">Gerado por: {{ $geradoPor }}</p>
    <p class="meta">Total: {{ $fretes->count() }} frete(s) | Limite por PDF: {{ $limiteRegistros }}</p>
    <p class="meta">
        Filtros:
        @if (($filtros['search'] ?? '') !== '')
            pesquisa "{{ $filtros['search'] }}"
        @else
            nenhum
        @endif
        @if (($filtros['status_situacao'] ?? null))
            · situação {{ $filtros['status_situacao'] }}
        @endif
    </p>

    <table>
        <thead>
            <tr>
                <th width="22%">Nome</th>
                <th width="12%">Valor</th>
                <th width="18%">Veículo (ID SBS)</th>
                <th width="10%">Situação</th>
                <th width="12%">Valor fruta/kg</th>
                <th width="26%">Descrição</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($fretes as $frete)
                <tr>
                    <td>{{ $frete->nome }}</td>
                    <td>{{ $fmtMoeda($frete->valor) }}</td>
                    <td>
                        @if ($frete->veiculo)
                            {{ $frete->veiculo->id_sbs }} — {{ $frete->veiculo->nome }}
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $frete->status_situacao }}</td>
                    <td>{{ $fmtMoeda($frete->valor_fruta_kg) }}</td>
                    <td>{{ $frete->descricao ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="empty">Nenhum frete corresponde aos filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
