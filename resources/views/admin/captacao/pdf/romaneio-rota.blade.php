@php
    $azul = $cores['azul'] ?? '#1A5FB4';
    $amarelo = $cores['amarelo'] ?? '#FBC02D';
    $verde = $cores['verde'] ?? '#2E7D32';
    $azulClaro = '#E8F2FC';
    $verdeClaro = '#EEF6EE';
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Romaneio — {{ $romaneio['rota_nome'] }}</title>
    <style>
        @page { margin: 10mm 8mm; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9px; color: #1a1a1a; margin: 0; }
        .faixa-topo { height: 4px; background: {{ $amarelo }}; margin-bottom: 8px; }
        .cabecalho { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .cabecalho td { vertical-align: middle; padding: 0; border: none; }
        .cabecalho-logo img { max-height: 48px; max-width: 160px; display: block; }
        .cabecalho-textos { text-align: center; padding: 0 8px; }
        .titulo-principal {
            font-size: 16px;
            font-weight: bold;
            color: {{ $azul }};
            margin: 0 0 3px;
            letter-spacing: 0.6px;
            text-transform: uppercase;
        }
        .titulo-rota {
            font-size: 12px;
            font-weight: bold;
            color: {{ $verde }};
            margin: 0;
            letter-spacing: 0.3px;
        }
        .meta-grid { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .meta-grid td {
            border: 1px solid {{ $azul }};
            padding: 5px 7px;
            vertical-align: middle;
            background: #fff;
        }
        .meta-label {
            font-weight: bold;
            font-size: 7px;
            text-transform: uppercase;
            color: {{ $verde }};
            letter-spacing: 0.4px;
            margin-bottom: 2px;
        }
        table.romaneio { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.romaneio th, table.romaneio td {
            border: 1px solid {{ $azul }};
            padding: 3px 4px;
            vertical-align: top;
        }
        table.romaneio td.col-carregamento,
        table.romaneio td.col-cliente {
            vertical-align: middle !important;
            text-align: center !important;
        }
        table.romaneio th {
            background: {{ $azul }};
            color: #fff;
            font-size: 8px;
            text-align: center;
            font-weight: bold;
        }
        .loja-bloco {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: avoid !important;
        }
        .loja-bloco > tbody > tr > td {
            padding: 0;
            border: none;
            vertical-align: top;
        }
        table.romaneio-loja {
            page-break-inside: avoid !important;
        }
        table.romaneio-loja tr {
            page-break-inside: avoid !important;
        }
        .col-carregamento { width: 8%; }
        .col-cliente { width: 18%; font-weight: bold; color: {{ $azul }}; }
        .col-item { width: 26%; }
        .col-obs { width: 8%; text-align: center; }
        .col-qtd, .col-cxs, .col-peso { width: 10%; text-align: right; }
        .linha-total td {
            font-weight: bold;
            background: {{ $verdeClaro }};
            color: {{ $verde }};
        }
        .linha-total-geral td {
            font-weight: bold;
            background: {{ $azulClaro }};
            color: {{ $azul }};
            font-size: 10px;
            border-top: 2px solid {{ $amarelo }};
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="faixa-topo"></div>

    <table class="cabecalho">
        <tr>
            <td class="cabecalho-logo" style="width: 28%;">
                @if (! empty($logoDataUri))
                    <img src="{{ $logoDataUri }}" alt="Sítio Barreiras">
                @endif
            </td>
            <td class="cabecalho-textos" style="width: 44%;">
                <h1 class="titulo-principal">Romaneio de viagem</h1>
                <h2 class="titulo-rota">{{ mb_strtoupper($romaneio['rota_nome'], 'UTF-8') }}</h2>
            </td>
            <td style="width: 28%;"></td>
        </tr>
    </table>

    <table class="meta-grid">
        <tr>
            <td style="width:20%">
                <div class="meta-label">Data de saída</div>
                {{ $lote->data_referencia->format('d/m/Y') }}
            </td>
            <td style="width:15%">
                <div class="meta-label">Hora de saída</div>
                —
            </td>
            <td style="width:35%">
                <div class="meta-label">Veículo</div>
                {{ $veiculoNome ?? '—' }}
            </td>
            <td style="width:30%">
                <div class="meta-label">Motorista</div>
                {{ trim((string) ($romaneio['motorista_nome'] ?? '')) !== '' ? $romaneio['motorista_nome'] : '—' }}
            </td>
        </tr>
    </table>

    <table class="romaneio romaneio-colunas">
        <colgroup>
            <col class="col-carregamento">
            <col class="col-cliente">
            <col class="col-item">
            <col class="col-obs">
            <col class="col-qtd">
            <col class="col-cxs">
            <col class="col-peso">
        </colgroup>
        <thead>
        <tr>
            <th class="col-carregamento">Carregamento</th>
            <th class="col-cliente">Cliente</th>
            <th class="col-item">Item</th>
            <th class="col-obs">Obs</th>
            <th class="col-qtd">Qtd</th>
            <th class="col-cxs">Cxs</th>
            <th class="col-peso">Peso</th>
        </tr>
        </thead>
    </table>

    @foreach ($romaneio['lojas'] as $loja)
        @php $qtdItens = count($loja['itens']); @endphp
        <table class="loja-bloco">
            <tr>
                <td>
                    <table class="romaneio romaneio-loja">
                        <colgroup>
                            <col class="col-carregamento">
                            <col class="col-cliente">
                            <col class="col-item">
                            <col class="col-obs">
                            <col class="col-qtd">
                            <col class="col-cxs">
                            <col class="col-peso">
                        </colgroup>
                        <tbody>
                        @foreach ($loja['itens'] as $indice => $item)
                            <tr>
                                @if ($indice === 0)
                                    <td rowspan="{{ $qtdItens + 1 }}" class="col-carregamento" style="vertical-align: middle; text-align: center;">
                                        {{ $loja['ordem_carregamento'] ?? '—' }}
                                    </td>
                                    <td rowspan="{{ $qtdItens + 1 }}" class="col-cliente" style="vertical-align: middle; text-align: center;">
                                        {{ $loja['cliente_nome'] }}
                                    </td>
                                @endif
                                <td class="col-item">{{ $item['fruta_nome'] }}</td>
                                <td class="col-obs">—</td>
                                <td class="col-qtd text-right">{{ $item['quantidade_um_formatado'] }}</td>
                                <td class="col-cxs text-right">{{ $item['caixas_formatado'] }}</td>
                                <td class="col-peso text-right">{{ $item['quantidade_kg_formatado'] }}</td>
                            </tr>
                        @endforeach
                        <tr class="linha-total">
                            <td class="text-right">Total</td>
                            <td></td>
                            <td class="text-right">
                                @foreach ($loja['totais_por_um'] as $totalUm)
                                    <div>{{ $totalUm['quantidade_formatado'] }} {{ $totalUm['unidade_medicao'] }}</div>
                                @endforeach
                            </td>
                            <td class="text-right">{{ $loja['total_caixas_formatado'] }}</td>
                            <td class="text-right">{{ $loja['total_kg_formatado'] }}</td>
                        </tr>
                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    @endforeach

    @if (! empty($romaneio['totais_gerais']))
        <table class="romaneio romaneio-total-geral">
            <colgroup>
                <col class="col-carregamento">
                <col class="col-cliente">
                <col class="col-item">
                <col class="col-obs">
                <col class="col-qtd">
                <col class="col-cxs">
                <col class="col-peso">
            </colgroup>
            <tbody>
            <tr class="linha-total-geral">
                <td colspan="2"></td>
                <td class="text-right">Total geral</td>
                <td></td>
                <td class="text-right">
                    @foreach ($romaneio['totais_gerais']['totais_por_um'] as $totalUm)
                        <div>{{ $totalUm['quantidade_formatado'] }} {{ $totalUm['unidade_medicao'] }}</div>
                    @endforeach
                </td>
                <td class="text-right">{{ $romaneio['totais_gerais']['total_caixas_formatado'] ?? '—' }}</td>
                <td class="text-right">{{ $romaneio['totais_gerais']['total_kg_formatado'] }}</td>
            </tr>
            </tbody>
        </table>
    @endif
</body>
</html>
