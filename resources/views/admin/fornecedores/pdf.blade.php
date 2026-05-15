<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Fornecedores</title>
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

    <h1>Relatório de Fornecedores</h1>
    <p class="meta">Gerado em: {{ $geradoEm->format('d/m/Y H:i:s') }}</p>
    <p class="meta">Gerado por: {{ $geradoPor }}</p>
    <p class="meta">Total: {{ $fornecedores->count() }} fornecedor(es) | Limite por PDF: {{ $limiteRegistros }}</p>
    <p class="meta">
        Filtros:
        @php
            $temFiltro = ($filtros['search'] ?? '') !== '' || ($filtros['id_estado'] ?? null) !== null;
            $estadoFiltroNome = null;
            if (($filtros['id_estado'] ?? null) !== null) {
                $estadoFiltroNome = \App\Models\Estado::query()->whereKey((int) $filtros['id_estado'])->value('nome');
            }
        @endphp
        @if ($temFiltro)
            @if (($filtros['search'] ?? '') !== '')
                pesquisa "{{ $filtros['search'] }}"
            @endif
            @if (($filtros['id_estado'] ?? null) !== null)
                @if (($filtros['search'] ?? '') !== '')
                    ·
                @endif
                estado "{{ $estadoFiltroNome ?? $filtros['id_estado'] }}"
            @endif
        @else
            nenhum
        @endif
    </p>

    <table>
        <thead>
            <tr>
                <th width="10%">ID CIGAM</th>
                <th width="18%">Estado (ICMS)</th>
                <th width="32%">Razão social</th>
                <th width="22%">Fantasia</th>
                <th width="18%">CPF/CNPJ</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($fornecedores as $fornecedor)
                <tr>
                    <td>{{ $fornecedor->id_cigam }}</td>
                    <td>{{ $fornecedor->estado?->nome ?? '—' }}</td>
                    <td>{{ $fornecedor->razao_social }}</td>
                    <td>{{ $fornecedor->fantasia ?? '-' }}</td>
                    <td>{{ $fmtDoc($fornecedor->cnpj_cpf) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="empty">Nenhum fornecedor corresponde aos filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
