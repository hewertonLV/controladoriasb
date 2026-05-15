<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório — Hub corporativo</title>
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

        $tipoDoc = fn ($t) => $t === 'FISICA' ? 'Física' : ($t === 'JURIDICA' ? 'Jurídica' : '—');
        $statusLinha = fn ($b) => $b ? 'Ativa' : 'Inativa';
    @endphp

    <h1>Hub corporativo</h1>
    <p class="meta">Gerado em: {{ $geradoEm->format('d/m/Y H:i:s') }}</p>
    <p class="meta">Gerado por: {{ $geradoPor }}</p>
    <p class="meta">Total: {{ $empresas->count() }} registro(s) | Limite por PDF: {{ $limiteRegistros }}</p>
    <p class="meta">
        Filtros:
        @if ($filtros['search'] !== '')
            pesquisa "{{ $filtros['search'] }}"
        @endif
        @if (($filtros['tipo_entidade'] ?? null) !== null)
            {{ $filtros['search'] !== '' ? ' | ' : '' }} tipo {{ $filtros['tipo_entidade'] }}
        @endif
        @if ($filtros['status'] !== null)
            {{ ($filtros['search'] !== '' || ($filtros['tipo_entidade'] ?? null) !== null) ? ' | ' : '' }} status {{ $filtros['status'] === '1' ? 'ativos' : 'unidades inativas' }}
        @endif
        @if ($filtros['search'] === '' && $filtros['status'] === null && ($filtros['tipo_entidade'] ?? null) === null)
            nenhum
        @endif
    </p>

    <table>
        <thead>
            <tr>
                <th width="10%">Tipo</th>
                <th width="8%">ID CIGAM</th>
                <th width="22%">Nome / Razão social</th>
                <th width="16%">Fantasia</th>
                <th width="14%">CPF/CNPJ</th>
                <th width="8%">UN ref.</th>
                <th width="8%">Tipo doc.</th>
                <th width="8%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($empresas as $empresa)
                @php
                    $d = $empresa->dadosConsolidadosParaAuditoria();
                @endphp
                <tr>
                    <td>{{ $empresa->rotuloTipoRegistro() }}</td>
                    <td>{{ $d['id_cigam'] ?? '' }}</td>
                    <td>{{ $d['nome_exibicao'] ?? '' }}</td>
                    <td>{{ $d['fantasia'] ?? '-' }}</td>
                    <td>{{ $fmtDoc($d['documento'] ?? '') }}</td>
                    <td>{{ ($d['unidade_referencia'] ?? '') !== '' ? $d['unidade_referencia'] : '—' }}</td>
                    <td>{{ $tipoDoc($d['tipo_pessoa'] ?? '') }}</td>
                    <td>{{ $statusLinha((bool) ($d['status'] ?? false)) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="empty">Nenhum registro corresponde aos filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
