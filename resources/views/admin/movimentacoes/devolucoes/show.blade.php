@extends('layouts.app')

@section('title', 'Devolução')
@section('page-title', 'Movimentação — Devolução')

@section('content')
    @php
        $itens = collect([$movimentacao]);
        $tipoLegivel = str_replace('_', ' ', (string) ($movimentacao->tipo_devolucao ?? ''));
        $cliente = $movimentacao->vendaOrigem?->empresaDestino?->nomeExibicao()
            ?? $movimentacao->empresaOrigem?->nomeExibicao()
            ?? '—';
        $observacao = trim((string) ($movimentacao->observacao ?? ''));
    @endphp

    <x-admin.flash-messages />

    <div class="card shadow-sm">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <h4 class="header-title mb-1">Detalhe da Devolução</h4>
                <div class="d-flex flex-wrap gap-2 text-muted small">
                    <span>Devolução #{{ $movimentacao->id }}</span>
                    <span>NF {{ $movimentacao->numero_nf_devolucao ?: '—' }}</span>
                    <span>{{ $movimentacao->data_movimentacao?->format('d/m/Y H:i') ?? '—' }}</span>
                </div>
            </div>
            <span class="badge bg-primary-subtle text-primary">{{ $movimentacao->status_registro }}</span>
        </div>

        <div class="card-body">
            <div class="mb-3">
                <h5 class="fs-14 text-uppercase text-muted mb-2">Resumo principal</h5>
                <div class="row g-2">
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded p-2 h-100">
                            <div class="small text-muted">Tipo</div>
                            <div class="fw-semibold">{{ $tipoLegivel !== '' ? $tipoLegivel : '—' }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded p-2 h-100">
                            <div class="small text-muted">NF venda origem</div>
                            <div class="fw-semibold">{{ $movimentacao->vendaOrigem?->vendaNota?->numero_nf ?? '—' }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded p-2 h-100">
                            <div class="small text-muted">Cliente</div>
                            <div class="fw-semibold text-truncate" title="{{ $cliente }}">{{ $cliente }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded p-2 h-100">
                            <div class="small text-muted">Unidade retorno</div>
                            <div class="fw-semibold text-truncate" title="{{ $movimentacao->unidadeRetorno?->nome ?? '—' }}">{{ $movimentacao->unidadeRetorno?->nome ?? '—' }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded p-2 h-100">
                            <div class="small text-muted">Destino estoque</div>
                            <div class="fw-semibold text-truncate" title="{{ $movimentacao->empresaDestino?->nomeExibicao() ?? '—' }}">{{ $movimentacao->empresaDestino?->nomeExibicao() ?? '—' }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded p-2 h-100">
                            <div class="small text-muted">Quantidade devolvida</div>
                            <div class="fw-semibold">{{ number_format((float) $movimentacao->qtd_fruta_um, 2, ',', '.') }} {{ $movimentacao->fruta?->unidade_medicao ?? 'UM' }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded p-2 h-100">
                            <div class="small text-muted">Valor devolvido</div>
                            <div class="fw-semibold">R$ {{ number_format((float) $movimentacao->valor_devolucao_total, 2, ',', '.') }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <div class="border rounded p-2 h-100">
                            <div class="small text-muted">Responsável</div>
                            <div class="fw-semibold">{{ $movimentacao->canceladaPor?->name ?? 'Não registrado' }}</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded p-2">
                            <div class="small text-muted">Motivo da devolução</div>
                            <div class="fw-semibold">{{ $movimentacao->motivo_devolucao ?: '—' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <h5 class="fs-14 text-uppercase text-muted mb-2">Itens da devolução</h5>
                <div class="table-responsive border rounded">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fruta</th>
                                <th>Quantidade</th>
                                <th>Unidade</th>
                                <th>Valor unitário</th>
                                <th>Valor total</th>
                                <th>Custo</th>
                                <th>Resultado</th>
                                <th>Motivo</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($itens as $item)
                                <tr>
                                    <td class="fw-semibold">{{ $item->fruta?->nome ?? '—' }}</td>
                                    <td>{{ number_format((float) $item->qtd_fruta_um, 2, ',', '.') }}</td>
                                    <td>{{ $item->fruta?->unidade_medicao ?? 'UM' }}</td>
                                    <td>R$ {{ number_format((float) $item->valor_devolucao_um, 2, ',', '.') }}</td>
                                    <td>R$ {{ number_format((float) $item->valor_devolucao_total, 2, ',', '.') }}</td>
                                    <td>R$ {{ number_format((float) $item->valor_custo_devolucao, 2, ',', '.') }}</td>
                                    <td>R$ {{ number_format((float) $item->resultado_devolucao, 2, ',', '.') }}</td>
                                    <td>{{ $item->motivo_devolucao ?: '—' }}</td>
                                    <td><span class="badge bg-light text-muted">{{ $item->status_registro }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="border rounded p-3 bg-light bg-opacity-50">
                <h5 class="fs-14 text-uppercase text-muted mb-2">Observações</h5>
                <p class="mb-0">{{ $observacao !== '' ? $observacao : 'Sem observações' }}</p>
            </div>
        </div>

        <div class="card-footer d-flex flex-wrap gap-2 justify-content-end">
            <a href="{{ route('admin.movimentacoes.devolucoes.index') }}" class="btn btn-light btn-sm">Voltar</a>
            @can('movimentacoes.devolucoes.editar')
                <a href="{{ route('admin.movimentacoes.devolucoes.edit', $movimentacao) }}" class="btn btn-primary btn-sm">Corrigir</a>
            @endcan
            @can('movimentacoes.devolucoes.cancelar-admin')
                @if ($movimentacao->status_registro === \App\Enums\MovimentacaoStatusRegistro::ATIVO->value)
                    <form method="POST"
                          action="{{ route('admin.movimentacoes.devolucoes.cancelar-admin', $movimentacao) }}"
                          class="d-flex flex-wrap gap-2"
                          data-confirm="Cancelar esta devolução administrativamente?"
                          data-confirm-title="Cancelar devolução"
                          data-confirm-variant="danger"
                          data-confirm-btn="Cancelar">
                        @csrf
                        <input name="motivo" class="form-control form-control-sm" required placeholder="Motivo do cancelamento administrativo" style="min-width: 280px;">
                        <button class="btn btn-danger btn-sm" type="submit">Cancelar devolução</button>
                    </form>
                @endif
            @endcan
        </div> 
    </div>
@endsection
