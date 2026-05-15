<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Clientes</title>
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
        $fmtDoc = function ($v) {
            $d = preg_replace('/\D/', '', (string) $v) ?? '';
            if (strlen($d) === 11) {
                return substr($d, 0, 3).'.'.substr($d, 3, 3).'.'.substr($d, 6, 3).'-'.substr($d, 9, 2);
            }
            if (strlen($d) === 14) {
                return substr($d, 0, 2).'.'.substr($d, 2, 3).'.'.substr($d, 5, 3).'/'.substr($d, 8, 4).'-'.substr($d, 12, 2);
            }
            return $d;
        };
    @endphp

    <h1>Relatório de Clientes</h1>
    <p class="meta">Gerado em: {{ $geradoEm->format('d/m/Y H:i:s') }}</p>
    <p class="meta">Gerado por: {{ $geradoPor }}</p>
    <p class="meta">Total: {{ $clientes->count() }} cliente(s) | Limite por PDF: {{ $limiteRegistros }}</p>
    <p class="meta">
        Filtros:
        @if ($filtros['search'] !== '')
            pesquisa "{{ $filtros['search'] }}"
        @else
            nenhum
        @endif
    </p>

    <table>
        <thead>
            <tr>
                <th width="10%">ID CIGAM</th>
                <th width="30%">Razão social</th>
                <th width="18%">CPF/CNPJ</th>
                <th width="8%">UN</th>
                <th width="14%">Praça</th>
                <th width="12%">Grupo</th>
                <th width="12%">Desconto NF</th>
                <th width="12%">Desconto contrato</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($clientes as $cliente)
                <tr>
                    <td>{{ $cliente->id_cigam }}</td>
                    <td>{{ $cliente->razao_social }}</td>
                    <td>{{ $fmtDoc($cliente->cnpj_cpf) }}</td>
                    <td>{{ $cliente->id_unidade_negocio }}</td>
                    <td>{{ $cliente->praca?->nome ?? '—' }}</td>
                    <td>{{ $cliente->grupo?->nome ?? '—' }}</td>
                    <td>{{ number_format((float) $cliente->desconto_nf, 2, ',', '.') }}</td>
                    <td>{{ number_format((float) $cliente->desconto_contrato, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="empty">Nenhum cliente corresponde aos filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
