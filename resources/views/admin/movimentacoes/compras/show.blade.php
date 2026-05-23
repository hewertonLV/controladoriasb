@extends('layouts.app')

@section('title', 'Compra #' . ($movimentacao->numero_compra ?? $movimentacao->id))
@section('page-title', 'Movimentação — Compra')

@section('content')
    <x-admin.flash-messages />

    <div class="card mb-3">
        <div class="card-header d-flex align-items-center flex-wrap gap-2">
            <h4 class="header-title mb-0">Compra #{{ $movimentacao->numero_compra ?? $movimentacao->id }}</h4>
            <span class="badge bg-secondary">v{{ $movimentacao->versao }}</span>
            @if ($movimentacao->movimentacao_origem_id)
                <span class="badge bg-info-subtle text-info">
                    Linha do tempo da compra #{{ $movimentacao->numero_compra ?? $movimentacao->movimentacao_origem_id }}
                </span>
            @endif
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="text-muted small">Data da movimentação</div>
                    <div class="fw-semibold">{{ $movimentacao->data_movimentacao?->format('d/m/Y H:i') ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Data da atualização desta versão</div>
                    <div class="fw-semibold">{{ $movimentacao->versao > 1 ? $movimentacao->created_at?->format('d/m/Y H:i') : '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Status do registro</div>
                    <div class="fw-semibold">{{ $movimentacao->status_registro }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">NF de compra</div>
                    <div class="fw-semibold">{{ $movimentacao->numero_nf_origem ?? '—' }}</div>
                </div>
                @if ($movimentacao->movimentacao_origem_id || $movimentacao->versaoAnterior)
                    <div class="col-md-6">
                        <div class="text-muted small">Número da compra na linha do tempo</div>
                        <div class="fw-semibold">
                            #{{ $movimentacao->numero_compra ?? $movimentacao->movimentacao_origem_id ?? $movimentacao->id }}
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted small">Versão anterior</div>
                        <div class="fw-semibold">
                            {{ $movimentacao->versaoAnterior ? '#'.$movimentacao->versaoAnterior->id : '—' }}
                        </div>
                    </div>
                @endif
                <div class="col-md-6">
                    <div class="text-muted small">Fornecedor (origem)</div>
                    <div class="fw-semibold">{{ $movimentacao->empresaOrigem?->nomeExibicao() ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Unidade (destino)</div>
                    <div class="fw-semibold">{{ $movimentacao->empresaDestino?->nomeExibicao() ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Fruta</div>
                    <div class="fw-semibold">{{ $movimentacao->fruta?->nome ?? '—' }}</div>
                </div>
                <div class="col-md-6">
                    <div class="text-muted small">Frete</div>
                    <div class="fw-semibold">{{ $movimentacao->frete?->nome ?? '—' }}</div>
                </div>
            </div>

            <hr class="my-4">

            <h5 class="mb-3">Valores informados e calculados</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="text-muted small">Quantidade (UM)</div>
                    <div class="fw-semibold">{{ number_format((float) $movimentacao->qtd_fruta_um, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Quantidade (kg)</div>
                    <div class="fw-semibold">{{ number_format((float) $movimentacao->qtd_fruta_kg, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Valor total NF</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->valor_nf_total, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Valor NF / UM</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->valor_nf_um, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Valor NF / kg</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->valor_nf_kg, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">ICMS convertido (kg)</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->icms_convertido_kg, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Rateio frete</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->valor_frete_rateio, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Frete / UM</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->valor_frete_um, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Frete / kg</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->valor_frete_kg, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Custo operacional (histórico)</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->valor_custo_operacional, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Saldo estoque (kg)</div>
                    <div class="fw-semibold">{{ number_format((float) $movimentacao->saldo_estoque_fruta_kg, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Saldo estoque (UM)</div>
                    <div class="fw-semibold">{{ number_format((float) $movimentacao->saldo_estoque_fruta_um, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Preço médio fruta (kg) — lote</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->preco_medio_fruta_kg, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-4">
                    <div class="text-muted small">Preço médio fruta (UM) — lote</div>
                    <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->preco_medio_fruta_um, 2, ',', '.') }}</div>
                </div>
            </div>

            @if ($movimentacao->status_registro === \App\Enums\MovimentacaoStatusRegistro::CANCELADO->value)
                <hr class="my-4">
                <div class="alert alert-warning mb-0">
                    <h5 class="alert-heading fs-14 mb-2">Compra cancelada</h5>
                    <p class="mb-1">
                        <strong>Cancelada em:</strong> {{ $movimentacao->cancelada_em?->format('d/m/Y H:i') ?? '—' }}
                    </p>
                    <p class="mb-1">
                        <strong>Responsável:</strong> {{ $movimentacao->canceladaPor?->name ?? '—' }}
                    </p>
                    <p class="mb-0">
                        <strong>Motivo:</strong> {{ $movimentacao->motivo_cancelamento ?: '—' }}
                    </p>
                </div>
            @endif
        </div>

        <div class="card-footer d-flex flex-wrap gap-2 justify-content-end align-items-center">
            @can('movimentacoes.compras.visualizar')
                <a href="{{ route('admin.movimentacoes.compras.index') }}" class="btn btn-light btn-sm">Voltar</a>
            @endcan
            @can('movimentacoes.compras.editar')
                @if ($movimentacao->status_registro === \App\Enums\MovimentacaoStatusRegistro::ATIVO->value)
                    <a href="{{ route('admin.movimentacoes.compras.edit', $movimentacao) }}" class="btn btn-primary btn-sm">Editar valor da NF</a>
                @endif
            @endcan
            @can('movimentacoes.compras.cancelar-admin')
                @if ($movimentacao->status_registro === \App\Enums\MovimentacaoStatusRegistro::ATIVO->value)
                    <form method="POST"
                          action="{{ route('admin.movimentacoes.compras.cancelar-admin', $movimentacao) }}"
                          class="d-flex flex-wrap gap-2"
                          data-confirm="Cancelar esta compra administrativamente? O estoque e os lançamentos posteriores serão recalculados."
                          data-confirm-title="Cancelar compra"
                          data-confirm-variant="danger"
                          data-confirm-btn="Cancelar">
                        @csrf
                        <input name="motivo"
                               class="form-control form-control-sm"
                               required
                               placeholder="Motivo do cancelamento administrativo"
                               style="min-width: 280px;">
                        <button class="btn btn-danger btn-sm" type="submit">Cancelar compra</button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    @can('movimentacoes.compras.cancelar-admin')
        @if ($movimentacao->status_registro === \App\Enums\MovimentacaoStatusRegistro::ATIVO->value)
            <div class="card mt-3 border-warning border-opacity-25">
                <div class="card-body">
                    <h5 class="fs-14 text-uppercase text-muted mb-2">O que é recalculado ao cancelar</h5>
                    <p class="text-muted small mb-2">
                        Ao confirmar o cancelamento, a compra deixa de participar dos cálculos vigentes
                        e o sistema reprocessa automaticamente:
                    </p>
                    <ul class="small mb-0">
                        <li>
                            <strong>Frete vinculado</strong> (se houver): rateio R$/kg entre compras, transferências e vendas
                            do mesmo frete; atualização de frete rateado e preço de aquisição do lote nas movimentações afetadas.
                        </li>
                        <li>
                            <strong>Entradas no destino</strong> (unidade + fruta desta compra): replay de compras ativas
                            e transferências recebidas conforme — snapshots de estoque, saldos e preço médio consolidado.
                        </li>
                        <li>
                            <strong>Linha do tempo completa</strong> da unidade/fruta: entradas e saídas vigentes posteriores
                            (vendas, devoluções com retorno, doações, descartes, transferências, conversões, entradas de produção)
                            em ordem cronológica.
                        </li>
                        <li>
                            <strong>Saldo atual em estoques</strong>: quantidade (kg/UM), preço médio e valor total acumulado.
                        </li>
                        <li>
                            <strong>Auditoria</strong>: registro do cancelamento com snapshots da movimentação e do estoque antes e depois.
                        </li>
                    </ul>
                </div>
            </div>
        @endif
    @endcan
@endsection
