@php
    use App\Support\Captacao\SaidaEstoqueFisicoCaptacaoService;

    /** @var \App\Models\Captacao\CaptacaoLote $lote */
    /** @var \Illuminate\Support\Collection<int, array<string, mixed>> $romaneioCarregamento */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Captacao\Pedido>|\Illuminate\Database\Eloquent\Collection $pedidosPorCliente */
    $saidaFisicaResolver = app(SaidaEstoqueFisicoCaptacaoService::class);
    $idGalpao = $saidaFisicaResolver->idGalpaoLote($lote);
    $idHub = $saidaFisicaResolver->idHubLote($lote);
    $nomeGalpao = $lote->unidadeGalpao?->nome ?? 'Galpão';
    $nomeFaturamento = $lote->unidadeFaturamento?->nome;
    $nomeHub = $lote->unidadeHubOrigem?->nome ?? 'HUB';
    $exibirRota = $exibirRota ?? true;
    $colunas = $exibirRota ? 8 : 7;
@endphp

<table class="table table-sm table-bordered align-middle mb-0 captacao-romaneio-tabela captacao-romaneio-saida-fisico-tabela">
    <thead>
    <tr>
        <th style="width:3.5rem">Ordem</th>
        <th>Loja</th>
        <th style="min-width:9rem">Saída física</th>
        @if ($exibirRota)
            <th>Rota</th>
        @endif
        <th>Fruta</th>
        <th>Qtd</th>
        <th>Unid.</th>
        <th>Qtd (kg)</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($romaneioCarregamento as $lojaIndice => $loja)
        @php
            $pedido = $pedidosPorCliente->get($loja['id_cliente']);
            $saidaAtual = $pedido !== null
                ? $saidaFisicaResolver->idSaidaEfetiva($pedido, $lote)
                : $idGalpao;
            $rotuloSaidaGalpao = 'Galpão — '.$nomeGalpao.($nomeFaturamento ? ' (fatur. '.$nomeFaturamento.')' : '');
            $rotuloSaidaHub = $idHub !== null ? 'HUB — '.$nomeHub : 'HUB não definido';
            $rotuloSaidaImpressao = $saidaAtual === $idHub && $idHub !== null
                ? $rotuloSaidaHub
                : $rotuloSaidaGalpao;
            $qtdLinhas = count($loja['itens']);
            $rowspan = $qtdLinhas + 1;
            $classeCorLoja = ($lojaIndice % 2 === 0) ? 'captacao-romaneio-loja-par' : 'captacao-romaneio-loja-impar';
        @endphp
        @foreach ($loja['itens'] as $indice => $item)
            <tr class="captacao-romaneio-linha-item {{ $classeCorLoja }}">
                @if ($indice === 0)
                    <td rowspan="{{ $rowspan }}" class="text-center fw-semibold">
                        {{ $loja['ordem_carregamento'] ?? '—' }}
                    </td>
                    <td rowspan="{{ $rowspan }}" class="text-center fw-semibold captacao-romaneio-col-loja">
                        <span class="captacao-romaneio-loja-text">{{ $loja['cliente_nome'] }}</span>
                    </td>
                    <td rowspan="{{ $rowspan }}" class="text-center">
                        <div class="captacao-romaneio-saida-impressao d-none">{{ $rotuloSaidaImpressao }}</div>
                        <div class="captacao-romaneio-saida-tela" data-saida-fisica-loja="{{ $loja['id_cliente'] }}">
                            <label class="form-check form-check-inline mb-0 me-2">
                                <input type="radio"
                                       class="form-check-input captacao-saida-fisica-radio"
                                       name="saida_fisica_{{ $loja['id_cliente'] }}"
                                       value="{{ $idGalpao }}"
                                       data-cliente="{{ $loja['id_cliente'] }}"
                                       data-url="{{ route('admin.captacao.lotes.pedidos.saida-fisica-venda', [$lote, $loja['id_cliente']]) }}"
                                       @checked($saidaAtual === $idGalpao)>
                                <span class="form-check-label small">{{ $rotuloSaidaGalpao }}</span>
                            </label>
                            @if ($idHub !== null)
                                <label class="form-check form-check-inline mb-0">
                                    <input type="radio"
                                           class="form-check-input captacao-saida-fisica-radio"
                                           name="saida_fisica_{{ $loja['id_cliente'] }}"
                                           value="{{ $idHub }}"
                                           data-cliente="{{ $loja['id_cliente'] }}"
                                           data-url="{{ route('admin.captacao.lotes.pedidos.saida-fisica-venda', [$lote, $loja['id_cliente']]) }}"
                                           @checked($saidaAtual === $idHub)>
                                    <span class="form-check-label small">{{ $rotuloSaidaHub }}</span>
                                </label>
                            @else
                                <span class="text-muted small d-block">HUB não definido.</span>
                            @endif
                            <span class="captacao-saida-fisica-status text-muted small" aria-live="polite"></span>
                        </div>
                    </td>
                    @if ($exibirRota)
                        <td rowspan="{{ $rowspan }}" class="text-center">{{ $loja['rota_nome'] ?? '—' }}</td>
                    @endif
                @endif
                <td class="captacao-romaneio-col-fruta">{{ $item['fruta_nome'] }}</td>
                <td class="text-end">{{ $item['quantidade_um_formatado'] }}</td>
                <td class="text-center">{{ $item['unidade_medicao'] }}</td>
                <td class="text-end">{{ $item['quantidade_kg_formatado'] }}</td>
            </tr>
        @endforeach
        <tr class="captacao-romaneio-linha-total {{ $classeCorLoja }}">
            <td class="text-end fw-semibold">Total da loja</td>
            <td class="text-end fw-semibold">
                @foreach ($loja['totais_por_um'] as $totalUm)
                    <div class="captacao-romaneio-total-um-linha">{{ $totalUm['quantidade_formatado'] }} {{ $totalUm['unidade_medicao'] }}</div>
                @endforeach
            </td>
            <td></td>
            <td class="text-end fw-semibold">{{ $loja['total_kg_formatado'] }}</td>
        </tr>
    @empty
        <tr><td colspan="{{ $colunas }}" class="text-muted text-center">Sem pedidos com quantidade.</td></tr>
    @endforelse
    </tbody>
    @if (($romaneioCarregamentoTotaisGerais ?? null) && $romaneioCarregamento->isNotEmpty())
        <tfoot>
        <tr class="fw-semibold captacao-romaneio-linha-total">
            <td colspan="{{ $exibirRota ? 5 : 4 }}" class="text-end">Total geral</td>
            <td class="text-end">
                @foreach ($romaneioCarregamentoTotaisGerais['totais_por_um'] as $totalUm)
                    <div class="captacao-romaneio-total-um-linha">{{ $totalUm['quantidade_formatado'] }} {{ $totalUm['unidade_medicao'] }}</div>
                @endforeach
            </td>
            <td></td>
            <td class="text-end">{{ $romaneioCarregamentoTotaisGerais['total_kg_formatado'] }}</td>
        </tr>
        </tfoot>
    @endif
</table>
