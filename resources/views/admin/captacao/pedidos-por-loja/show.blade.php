@extends('layouts.app')

@section('title', ($cliente->fantasia ?: $cliente->razao_social).' — pedido')
@section('page-title', $cliente->fantasia ?: $cliente->razao_social)

@push('head')
    <style>
        .captacao-pedido-loja-compact .card { margin-bottom: 0.5rem; }
        .captacao-pedido-loja-compact .card-header { padding: 0.3rem 0.6rem; font-size: 0.875rem; }
        .captacao-pedido-loja-compact .card-footer { padding: 0.35rem 0.6rem; }
        .captacao-pedido-loja-compact .table { font-size: 0.8rem; margin-bottom: 0; }
        .captacao-pedido-loja-compact .table > :not(caption) > * > * { padding: 0.15rem 0.3rem; vertical-align: middle; }
        .captacao-pedido-loja-compact .table thead th { font-weight: 600; white-space: nowrap; }
        .captacao-pedido-loja-compact .form-control-sm {
            padding: 0.1rem 0.25rem;
            font-size: 0.8rem;
            min-height: 1.45rem;
        }
        .captacao-pedido-loja-compact .rent-seta { font-size: 0.95rem; line-height: 1; vertical-align: -1px; }
    </style>
@endpush

@section('content')
    <div class="page-container captacao-pedido-loja-compact">
        @if ($pedidoAnterior && $linhasUltimoPedido->isNotEmpty())
            <div class="card border-secondary">
                <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center gap-1">
                    <span>
                        <strong>Último pedido</strong>
                        <span class="text-muted">{{ $pedidoAnterior->lote?->data_referencia?->format('d/m/Y') }}</span>
                    </span>
                    @if ($rentabilidadeUltimoPedido['margem_percentual'] !== null)
                        @php
                            $rentPct = (float) $rentabilidadeUltimoPedido['margem_percentual'];
                            $rentBadge = $rentPct >= 0 ? 'bg-success' : 'bg-danger';
                            $rentSeta = $rentPct >= 0 ? 'ri-arrow-up-line' : 'ri-arrow-down-line';
                        @endphp
                        <span class="badge {{ $rentBadge }}">
                            <i class="{{ $rentSeta }} rent-seta me-1"></i>
                            Rent. {{ number_format($rentPct, 2, ',', '.') }}%
                        </span>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Fruta</th>
                            <th class="text-end">Qtd</th>
                            <th class="text-end">Preço</th>
                            <th class="text-end">Rent.%</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($linhasUltimoPedido as $linha)
                            @php
                                $item = $linha['item'];
                                $rent = $linha['rentabilidade'];
                                $pctItem = $rent['margem_percentual'] !== null ? (float) $rent['margem_percentual'] : null;
                            @endphp
                            <tr>
                                <td class="text-truncate" style="max-width:12rem" title="{{ $item->fruta?->nome }}">
                                    {{ $item->fruta?->nome }}
                                    <span class="text-muted">({{ $item->fruta?->unidade_medicao }})</span>
                                </td>
                                <td class="text-end text-nowrap">{{ rtrim(rtrim($item->quantidade, '0'), '.') }}</td>
                                <td class="text-end text-nowrap">
                                    @if ($item->preco_venda !== null)
                                        {{ number_format((float) $item->preco_venda, 2, ',', '.') }}
                                    @else — @endif
                                </td>
                                <td class="text-end text-nowrap {{ $pctItem !== null && $pctItem < 0 ? 'text-danger' : ($pctItem !== null ? 'text-success' : '') }}">
                                    @if ($rent['margem_percentual'] !== null)
                                        <i class="ri-{{ $pctItem >= 0 ? 'arrow-up' : 'arrow-down' }}-line rent-seta me-1"></i>{{ number_format($pctItem, 2, ',', '.') }}%
                                    @else — @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        @if ($rentabilidadeUltimoPedido['faturamento'] !== '0.00')
                        <tfoot class="table-light">
                        <tr>
                            <th colspan="3" class="text-end">Totais</th>
                            <th class="text-end">
                                @if ($rentabilidadeUltimoPedido['margem_percentual'] !== null)
                                    @php $rentTotalPct = (float) $rentabilidadeUltimoPedido['margem_percentual']; @endphp
                                    <span class="{{ $rentTotalPct >= 0 ? 'text-success' : 'text-danger' }}">
                                        <i class="ri-{{ $rentTotalPct >= 0 ? 'arrow-up' : 'arrow-down' }}-line rent-seta me-1"></i>{{ number_format($rentTotalPct, 2, ',', '.') }}%
                                    </span>
                                @else — @endif
                            </th>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-muted text-end py-0 small">
                                Fat. {{ number_format((float) $rentabilidadeUltimoPedido['faturamento'], 2, ',', '.') }}
                                @if ((float) $cliente->desconto_nf > 0)
                                    (líquido após desc. NF {{ number_format((float) $cliente->desconto_nf, 2, ',', '.') }}%)
                                @endif
                            </td>
                        </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        @elseif ($possuiFrutas)
            <div class="alert alert-secondary py-1 px-2 mb-2 small">
                Sem pedido anterior nesta carteira.
            </div>
        @endif

        @if ($possuiFrutas)
        <form id="form-pedido-loja" method="post" action="{{ route('admin.captacao.pedidos-por-loja.salvar', [$lote, $cliente]) }}">
            @csrf
            @method('PUT')
        </form>
        @endif

        <div class="mb-2 d-flex flex-wrap gap-2 align-items-center">
            <a href="{{ route('admin.captacao.pedidos-por-loja.lojas', $lote) }}" class="btn btn-sm btn-light py-0">
                <i class="ri-arrow-left-line"></i> Lojas
            </a>
            @if ($possuiFrutas && $podeEditar)
                <button type="submit" form="form-pedido-loja" class="btn btn-sm btn-primary py-0">
                    Sincronizar
                </button>
                <form method="post"
                      action="{{ route('admin.captacao.pedidos-por-loja.captacao-concluida', [$lote, $cliente]) }}"
                      class="d-inline">
                    @csrf
                    <input type="hidden" name="captacao_concluida" value="{{ $pedidoAtual?->captacao_concluida ? '0' : '1' }}">
                    <button type="submit" class="btn btn-sm py-0 {{ $pedidoAtual?->captacao_concluida ? 'btn-warning' : 'btn-success' }}">
                        {{ $pedidoAtual?->captacao_concluida ? 'Reabrir pedido' : 'Finalizar pedido' }}
                    </button>
                </form>
            @endif
        </div>

        @if (! $possuiFrutas)
            <div class="alert alert-warning py-2 small mb-2">
                Sem frutas vinculadas.
                <a href="{{ route('admin.captacao.frutas-por-loja.show', $cliente) }}">Frutas por loja</a>
            </div>
        @endif

        @if ($possuiFrutas)
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center gap-1">
                    <span>
                        <strong>Hoje</strong>
                        <span class="text-muted">{{ $lote->data_referencia?->format('d/m/Y') }}</span>
                    </span>
                    @if ($cliente->percentual_margem_alvo !== null)
                        <span class="badge bg-light text-dark">Alvo {{ $cliente->percentual_margem_alvo }}%</span>
                    @endif
                    @if ((float) $cliente->desconto_nf > 0)
                        <span class="badge bg-light text-dark" title="Desconto NF aplicado na rentabilidade">
                            Desc. NF {{ number_format((float) $cliente->desconto_nf, 2, ',', '.') }}%
                        </span>
                    @endif
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                        <tr>
                            <th>Fruta</th>
                            <th class="text-end" style="width:4.5rem">Qtd</th>
                            <th class="text-end">Custo</th>
                            <th class="text-end" style="width:5rem">Venda</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach ($linhas as $i => $linha)
                            <tr>
                                <td class="text-truncate" style="max-width:12rem" title="{{ $linha['fruta']->nome }}">
                                    {{ $linha['fruta']->nome }}
                                    <span class="text-muted">({{ $linha['fruta']->unidade_medicao }})</span>
                                </td>
                                <td class="text-end">
                                    <input type="number" step="0.001" min="0" name="itens[{{ $i }}][quantidade]"
                                           form="form-pedido-loja"
                                           class="form-control form-control-sm text-end"
                                           value="{{ $linha['item_atual'] ? rtrim(rtrim($linha['item_atual']->quantidade, '0'), '.') : '' }}"
                                           @disabled(! $podeEditar)>
                                    <input type="hidden" name="itens[{{ $i }}][id_fruta]" form="form-pedido-loja" value="{{ $linha['fruta']->id }}">
                                </td>
                                <td class="text-end text-muted text-nowrap">
                                    @if ($linha['custo'] !== null)
                                        {{ number_format((float) $linha['custo'], 2, ',', '.') }}
                                    @else
                                        <span class="text-warning" title="Sem estoque no galpão">s/ est.</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <input type="number" step="0.01" min="0" name="itens[{{ $i }}][preco_venda]"
                                           form="form-pedido-loja"
                                           class="form-control form-control-sm text-end"
                                           value="{{ $linha['item_atual']?->preco_venda !== null ? rtrim(rtrim($linha['item_atual']->preco_venda, '0'), '.') : '' }}"
                                           @disabled(! $podeEditar)>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
@endsection
