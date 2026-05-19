@extends('layouts.app')

@section('title', 'Venda')
@section('page-title', 'Movimentação — Venda')

@section('content')
    @php
        $itensAtivos = $itens->where('status_registro', \App\Enums\MovimentacaoStatusRegistro::ATIVO->value);
    @endphp

    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <h4 class="header-title mb-0">Venda {{ $movimentacao->vendaNota?->numero_nf ? 'NF '.$movimentacao->vendaNota->numero_nf : '#'.$movimentacao->id }}</h4>
            <a href="{{ route('admin.movimentacoes.vendas.index') }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
            @can('movimentacoes.vendas.editar')
                <a href="{{ route('admin.movimentacoes.vendas.edit', $movimentacao) }}" class="btn btn-primary btn-sm">Corrigir</a>
            @endcan
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><strong>NF:</strong><br>{{ $movimentacao->vendaNota?->numero_nf ?? '—' }}</div>
                <div class="col-md-3"><strong>Status:</strong><br>{{ $movimentacao->status_registro }}</div>
                <div class="col-md-3"><strong>Data:</strong><br>{{ $movimentacao->data_movimentacao?->format('d/m/Y H:i') }}</div>
                <div class="col-md-3"><strong>Itens:</strong><br>{{ $itens->count() }}</div>
                <div class="col-md-4"><strong>Origem:</strong><br>{{ $movimentacao->empresaOrigem?->nomeExibicao() ?? '—' }}</div>
                <div class="col-md-4"><strong>Cliente:</strong><br>{{ $movimentacao->empresaDestino?->nomeExibicao() ?? '—' }}</div>
                <div class="col-md-4"><strong>Faturamento:</strong><br>{{ $movimentacao->unidadeFaturamento?->nome ?? '—' }}</div>
                <div class="col-md-3"><strong>Valor vendido ativo:</strong><br>R$ {{ number_format((float) $itensAtivos->sum('valor_nf_total'), 2, ',', '.') }}</div>
                <div class="col-md-3"><strong>Custo saída ativo:</strong><br>R$ {{ number_format((float) $itensAtivos->sum('valor_custo_saida'), 2, ',', '.') }}</div>
                <div class="col-md-3"><strong>Frete rateio ativo:</strong><br>R$ {{ number_format((float) $itensAtivos->sum('valor_frete_rateio'), 2, ',', '.') }}</div>
                <div class="col-md-3"><strong>Resultado ativo:</strong><br>R$ {{ number_format((float) $itensAtivos->sum('resultado_movimentacao'), 2, ',', '.') }}</div>
            </div>

            <hr>

            <h5 class="mb-3">Frutas vendidas nesta venda</h5>
            <div class="row g-3">
                @forelse ($itens as $item)
                    <div class="col-12 col-xl-6">
                        <div class="card border h-100 mb-0">
                            <div class="card-body">
                                <div class="d-flex flex-wrap align-items-start gap-2 mb-3">
                                    <div class="me-auto">
                                        <h5 class="mb-1">{{ $item->fruta?->nome ?? '—' }}</h5>
                                        <div class="text-muted small">
                                            Movimentação #{{ $item->id }} · Versão {{ $item->versao }} · {{ $item->status_registro }}
                                        </div>
                                    </div>
                                    @if ($item->status_registro === \App\Enums\MovimentacaoStatusRegistro::ATIVO->value)
                                        <div class="d-flex flex-wrap gap-1 justify-content-end">
                                            @can('movimentacoes.vendas.editar')
                                            <a href="{{ route('admin.movimentacoes.vendas.edit', $item) }}" class="btn btn-soft-primary btn-sm">
                                                <i class="ri-pencil-line me-1"></i> Corrigir item
                                            </a>
                                            @endcan
                                            @can('movimentacoes.vendas.cancelar-admin')
                                                <form method="POST"
                                                      action="{{ route('admin.movimentacoes.vendas.cancelar-item-admin', $item) }}"
                                                      data-confirm="Cancelar apenas este item da venda?"
                                                      data-confirm-title="Cancelar item"
                                                      data-confirm-variant="danger"
                                                      data-confirm-btn="Cancelar item"
                                                      data-confirm-prompt="Informe o motivo do cancelamento"
                                                      data-confirm-prompt-field="motivo">
                                                    @csrf
                                                    <input type="hidden" name="motivo">
                                                    <button type="submit" class="btn btn-soft-danger btn-sm">
                                                        <i class="ri-close-circle-line me-1"></i> Cancelar item
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
                                    @endif
                                </div>
                                <div class="row g-2 small">
                                    <div class="col-6 col-md-4"><span class="text-muted d-block">Qtd UM</span><strong>{{ number_format((float) $item->qtd_fruta_um, 2, ',', '.') }} {{ $item->fruta?->unidade_medicao ?? '' }}</strong></div>
                                    <div class="col-6 col-md-4"><span class="text-muted d-block">Qtd kg</span><strong>{{ number_format((float) $item->qtd_fruta_kg, 2, ',', '.') }}</strong></div>
                                    <div class="col-6 col-md-4"><span class="text-muted d-block">Preço médio kg</span><strong>R$ {{ number_format((float) $item->preco_medio_fruta_kg, 2, ',', '.') }}</strong></div>
                                    <div class="col-6 col-md-4"><span class="text-muted d-block">Valor vendido</span><strong>R$ {{ number_format((float) $item->valor_nf_total, 2, ',', '.') }}</strong></div>
                                    <div class="col-6 col-md-4"><span class="text-muted d-block">Custo saída</span><strong>R$ {{ number_format((float) $item->valor_custo_saida, 2, ',', '.') }}</strong></div>
                                    <div class="col-6 col-md-4"><span class="text-muted d-block">Frete rateio</span><strong>R$ {{ number_format((float) $item->valor_frete_rateio, 2, ',', '.') }}</strong></div>
                                    <div class="col-6 col-md-4"><span class="text-muted d-block">Resultado</span><strong>R$ {{ number_format((float) $item->resultado_movimentacao, 2, ',', '.') }}</strong></div>
                                    <div class="col-12 col-md-8"><span class="text-muted d-block">Origem / Cliente</span><strong>{{ $item->empresaOrigem?->nomeExibicao() ?? '—' }} → {{ $item->empresaDestino?->nomeExibicao() ?? '—' }}</strong></div>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12">
                        <div class="alert alert-warning mb-0">Nenhum item encontrado para esta venda.</div>
                    </div>
                @endforelse
            </div>

            @can('movimentacoes.vendas.cancelar-admin')
                @if ($movimentacao->status_registro === \App\Enums\MovimentacaoStatusRegistro::ATIVO->value)
                    <hr>
                    <form method="POST"
                          action="{{ route('admin.movimentacoes.vendas.cancelar-admin', $movimentacao) }}"
                          class="row g-2"
                          data-confirm="Cancelar esta venda completa e estornar todos os itens?"
                          data-confirm-title="Cancelar venda"
                          data-confirm-variant="danger"
                          data-confirm-btn="Cancelar venda">
                        @csrf
                        <div class="col-md-10">
                            <input name="motivo" class="form-control" required placeholder="Motivo do cancelamento administrativo da venda completa">
                        </div>
                        <div class="col-md-2 d-grid">
                            <button class="btn btn-danger" type="submit">Cancelar venda completa</button>
                        </div>
                    </form>
                @endif
            @endcan
        </div>
    </div>
@endsection
