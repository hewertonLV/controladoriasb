<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório de Unidades de Negócio</title>
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

    <h1>Relatório de Unidades de Negócio</h1>
    <p class="meta">Gerado em: {{ $geradoEm->format('d/m/Y H:i:s') }}</p>
    <p class="meta">Gerado por: {{ $geradoPor }}</p>
    <p class="meta">Total: {{ $unidadesNegocio->count() }} unidade(s) | Limite por PDF: {{ $limiteRegistros }}</p>
    <p class="meta">
        Filtros:
        @php
            $partesFiltro = [];
            if (($filtros['search'] ?? '') !== '') {
                $partesFiltro[] = 'pesquisa "'.$filtros['search'].'"';
            }
            if (($filtros['status'] ?? null) !== null) {
                $partesFiltro[] = 'status '.($filtros['status'] === '1' ? 'ativas' : 'inativas');
            }
            if (($filtros['possui_estoque'] ?? null) !== null) {
                $partesFiltro[] = 'estoque '.($filtros['possui_estoque'] === '1' ? 'sim' : 'não');
            }
            if (($filtros['id_estado'] ?? null) !== null) {
                $nomeEstadoFiltro = \App\Models\Estado::query()->whereKey((int) $filtros['id_estado'])->value('nome');
                $partesFiltro[] = 'estado '.($nomeEstadoFiltro ?? $filtros['id_estado']);
            }
        @endphp
        {{ $partesFiltro === [] ? 'nenhum' : implode(' | ', $partesFiltro) }}
    </p>

    <table>
        <thead>
            <tr>
                <th width="8%">ID CIGAM</th>
                <th width="12%">Estado</th>
                <th width="22%">Razão social</th>
                <th width="18%">Nome</th>
                <th width="14%">CPF/CNPJ</th>
                <th width="8%">Custo op.</th>
                <th width="6%">Estoque</th>
                <th width="6%">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($unidadesNegocio as $unidade)
                <tr>
                    <td>{{ $unidade->id_cigam }}</td>
                    <td>{{ $unidade->estado?->nome ?? '—' }}</td>
                    <td>{{ $unidade->razao_social }}</td>
                    <td>{{ $unidade->nome }}</td>
                    <td>{{ $fmtDoc($unidade->cpf_cnpj) }}</td>
                    <td>{{ number_format((float) $unidade->custo_operacional, 2, ',', '.') }}</td>
                    <td>{{ $unidade->possui_estoque ? 'Sim' : 'Não' }}</td>
                    <td>{{ $unidade->status ? 'Ativa' : 'Inativa' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="empty">Nenhuma unidade corresponde aos filtros aplicados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
