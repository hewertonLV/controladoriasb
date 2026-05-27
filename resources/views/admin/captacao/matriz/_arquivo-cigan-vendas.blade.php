@php
    /** @var \App\Models\Captacao\CaptacaoLote $lote */
    /** @var list<array{id_cliente: int, loja_nome: string, itens_captados: int, movimentacoes: int, numero_nf: string|null, completo: bool}> $resumoVendasLote */
    $resumoVendasLote = $resumoVendasLote ?? [];
    $vendasLotePendentes = collect($resumoVendasLote)->contains(fn (array $linha) => ! $linha['completo']);
    $lote->loadMissing([
        'unidadeFaturamento',
        'unidadeGalpao',
        'unidadeHubOrigem',
        'pedidos.cliente',
        'pedidos.unidadeSaidaVenda',
        'pedidos.itens.fruta',
    ]);
    $nomeGalpaoPadrao = $lote->unidadeGalpao?->nome ?? 'Galpão';
@endphp

<div class="border rounded p-3 mb-3">
    <h6 class="mb-1">Vendas — arquivo para o Cigam</h6>
    <p class="text-muted small mb-3">
        TXT com as quantidades captadas por loja. Origem: <strong>{{ $lote->unidadeFaturamento?->nome }}</strong>
        (faturamento). Destino: cada loja do lote. Um registro de NF por loja.
    </p>

    @can(\App\Enums\Permissions::CAPTACAO_LOTE_FATURAMENTO_INICIAR)
        <a href="{{ route('admin.captacao.lotes.arquivo-cigan-vendas', $lote) }}"
           class="btn btn-primary btn-sm">
            <i class="ri-download-2-line me-1"></i> Baixar arquivo de vendas
        </a>
    @else
        <p class="small text-warning mb-0">Sem permissão para baixar o arquivo de vendas.</p>
    @endcan

    <div class="border rounded p-3 mt-3 bg-light-subtle">
        <h6 class="mb-2">NF de venda</h6>
        <p class="text-muted small mb-3">
            Após importar o TXT no Cigam, envie aqui a NF gerada (XML, PDF ou TXT). O sistema
            <strong>efetiva as movimentações de venda</strong> no SB. Em seguida, vincule as rotas (aba
            <strong>Rotas</strong>) e a ordem de carregamento (aba <strong>Por rota</strong>) e clique em
            <strong>Concluir rotas e carregamento</strong>.
        </p>
        @if ($lote->possuiNfVenda())
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                @can(\App\Enums\Permissions::CAPTACAO_LOTE_VENDA_FINALIZAR)
                    <a href="{{ route('admin.captacao.lotes.nf-venda-cigan.download', $lote) }}"
                       class="btn btn-soft-success btn-sm">
                        <i class="ri-file-download-line me-1"></i>
                        Baixar NF enviada
                        @if ($lote->arquivo_nf_venda_nome)
                            ({{ $lote->arquivo_nf_venda_nome }})
                        @endif
                    </a>
                @endcan
                @if ($lote->nf_venda_enviada_em)
                    <span class="small text-muted">
                        Enviada em {{ $lote->nf_venda_enviada_em->format('d/m/Y H:i') }}
                    </span>
                @endif
            </div>
        @endif
        @if ($lote->status->permiteUploadNfVendaCigan())
            @can(\App\Enums\Permissions::CAPTACAO_LOTE_VENDA_FINALIZAR)
                <form method="post"
                      action="{{ route('admin.captacao.lotes.nf-venda-cigan.upload', $lote) }}"
                      enctype="multipart/form-data"
                      class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-8">
                        <label class="form-label" for="arquivo-nf-venda">Arquivo da NF</label>
                        <input type="file"
                               name="arquivo_nf_venda"
                               id="arquivo-nf-venda"
                               class="form-control form-control-sm @error('arquivo_nf_venda') is-invalid @enderror"
                               accept=".xml,.pdf,.txt,application/xml,text/xml,application/pdf,text/plain"
                               required>
                        @error('arquivo_nf_venda')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                        @error('status')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                        @error('saida_fisica')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                        @error('vendas')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-success btn-sm w-100">
                            <i class="ri-upload-2-line me-1"></i> Enviar NF e efetivar vendas no SB
                        </button>
                    </div>
                </form>
            @else
                <p class="small text-warning mb-0">Sem permissão para enviar a NF de venda.</p>
            @endcan
        @elseif ($lote->status === \App\Enums\CaptacaoLoteStatus::VincularRotasNosPedidos)
            <p class="small text-warning mb-0">
                NF enviada e vendas movimentadas no SB. Vincule rotas (aba <strong>Rotas</strong>) e ordem de carregamento
                (aba <strong>Por rota</strong>). Depois clique em <strong>Concluir rotas e carregamento</strong> no topo do lote.
            </p>
        @elseif ($lote->status === \App\Enums\CaptacaoLoteStatus::VincularFreteVenda)
            <p class="small text-warning mb-0">
                Rotas concluídas. Vincule frete por loja na aba <strong>Frete Vendas</strong> (opcional) e clique em
                <strong>Concluir frete venda</strong> no topo do lote.
            </p>
        @elseif ($lote->status === \App\Enums\CaptacaoLoteStatus::VendasFinalizadas)
            <p class="small text-success mb-0">
                Ciclo do lote concluído. Use o botão acima para baixar a NF; frete de vendas pode ser consultado na aba <strong>Frete Vendas</strong>.
            </p>
        @endif
    </div>

    @if ($lote->possuiNfVenda() && $resumoVendasLote !== [])
        <div class="border rounded p-3 mt-3 {{ $vendasLotePendentes ? 'border-warning' : 'border-success' }}">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <h6 class="mb-0">Movimentação de venda no SB</h6>
                @if ($vendasLotePendentes)
                    <span class="badge bg-warning-subtle text-warning">Pendências</span>
                @else
                    <span class="badge bg-success-subtle text-success">Completo</span>
                @endif
                @if ($vendasLotePendentes)
                    @can(\App\Enums\Permissions::CAPTACAO_LOTE_VENDA_FINALIZAR)
                        <form method="post"
                              action="{{ route('admin.captacao.lotes.pipeline.sincronizar-vendas-pendentes', $lote) }}"
                              class="ms-auto">
                            @csrf
                            <button type="submit" class="btn btn-warning btn-sm">
                                <i class="ri-refresh-line me-1"></i> Gerar vendas pendentes
                            </button>
                        </form>
                    @endcan
                @endif
            </div>
            <p class="text-muted small mb-2">
                Uma NF de venda por loja com quantidade captada. Compare itens da captação com movimentações em
                <strong>Movimentação → Vendas</strong> (busque por <code>CAP-{{ $lote->data_referencia->format('Ymd') }}-{{ $lote->id }}-</code>…).
            </p>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                    <tr>
                        <th>Loja</th>
                        <th>NF SB</th>
                        <th class="text-end">Itens captados</th>
                        <th class="text-end">Movimentações</th>
                        <th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($resumoVendasLote as $linha)
                        <tr @class(['table-warning' => ! $linha['completo']])>
                            <td>{{ $linha['loja_nome'] }}</td>
                            <td>
                                @if ($linha['numero_nf'])
                                    <code class="small">{{ $linha['numero_nf'] }}</code>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-end">{{ $linha['itens_captados'] }}</td>
                            <td class="text-end">{{ $linha['movimentacoes'] }}</td>
                            <td>
                                @if ($linha['completo'])
                                    <span class="text-success small">OK</span>
                                @else
                                    <span class="text-warning small">Pendente</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($lote->pedidos->isNotEmpty())
        <div class="table-responsive mt-3">
            <table class="table table-sm mb-0" id="arquivo-cigan-vendas-tabela">
                <thead>
                <tr>
                    <th>Saída física</th>
                    <th>Loja</th>
                    <th>Fruta</th>
                    <th class="text-end">Qtd</th>
                    <th>UM</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($lote->pedidos as $pedido)
                    @php
                        $itens = $pedido->itens->filter(static fn ($i) => (float) $i->quantidade > 0);
                        $saidaFisicaNome = $pedido->unidadeSaidaVenda?->nome ?? $nomeGalpaoPadrao;
                    @endphp
                    @forelse ($itens as $item)
                        <tr>
                            @if ($loop->first)
                                <td rowspan="{{ $itens->count() }}"
                                    class="align-middle text-nowrap"
                                    data-arquivo-cigan-vendas-saida="{{ $pedido->id_cliente }}">
                                    {{ $saidaFisicaNome }}
                                </td>
                                <td rowspan="{{ $itens->count() }}" class="align-middle">
                                    {{ $pedido->cliente?->fantasia ?: $pedido->cliente?->razao_social }}
                                </td>
                            @endif
                            <td>{{ $item->fruta?->nome }}</td>
                            <td class="text-end">{{ number_format((float) $item->quantidade, 2, ',', '.') }}</td>
                            <td>{{ $item->fruta?->unidade_medicao ?? '—' }}</td>
                        </tr>
                    @empty
                    @endforelse
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-muted small mt-2 mb-0">
            Apenas itens com quantidade &gt; 0 entram no arquivo. O faturamento no Cigam é sempre pela unidade de faturamento;
            a coluna «Saída física» indica de qual estoque o SB debitará na venda.
        </p>
    @else
        <p class="text-muted small mt-2 mb-0">Nenhum pedido no lote.</p>
    @endif
</div>
