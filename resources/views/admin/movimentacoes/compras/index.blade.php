@extends('layouts.app')

@section('title', 'Compras')
@section('page-title', 'Movimentação — Compra')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center flex-wrap gap-2">
            <div>
                <h4 class="header-title mb-0">Compras</h4>
                <p class="text-muted mb-0 small">Movimentações da categoria compra (versão ativa).</p>
            </div>
            @can('movimentacoes.compras.criar')
                <a href="{{ route('admin.movimentacoes.compras.create') }}" class="btn btn-primary btn-sm ms-auto">
                    <i class="ri-add-line me-1"></i> Nova compra
                </a>
            @endcan
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Compra</th>
                            <th>Data</th>
                            <th>Fornecedor</th>
                            <th>Unidade</th>
                            <th>Fruta</th>
                            <th class="text-end">Qtd (UM)</th>
                            <th class="text-end">Qtd (kg)</th>
                            <th class="text-end">Valor NF</th>
                            <th>Frete</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movimentacoes as $m)
                            <tr>
                                <td class="fw-semibold">Compra #{{ $m->numero_compra ?? $m->id }}</td>
                                <td>{{ $m->data_movimentacao?->format('d/m/Y H:i') }}</td>
                                <td>{{ $m->empresaOrigem?->nomeExibicao() ?? '—' }}</td>
                                <td>{{ $m->empresaDestino?->nomeExibicao() ?? '—' }}</td>
                                <td>{{ $m->fruta?->nome ?? '—' }}</td>
                                <td class="text-end">{{ number_format((float) $m->qtd_fruta_um, 2, ',', '.') }}</td>
                                <td class="text-end">{{ number_format((float) $m->qtd_fruta_kg, 2, ',', '.') }}</td>
                                <td class="text-end">R$ {{ number_format((float) $m->valor_nf_total, 2, ',', '.') }}</td>
                                <td>{{ $m->frete?->nome ?? '—' }}</td>
                                <td class="text-end">
                                    @can('movimentacoes.compras.visualizar')
                                        <a href="{{ route('admin.movimentacoes.compras.show', $m) }}" class="btn btn-light btn-sm">Ver</a>
                                    @endcan
                                    @can('movimentacoes.compras.editar')
                                        <a href="{{ route('admin.movimentacoes.compras.edit', $m) }}" class="btn btn-light btn-sm">Editar</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4">Nenhuma compra registrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($movimentacoes->hasPages())
            <div class="card-footer">
                {{ $movimentacoes->links() }}
            </div>
        @endif
    </div>
@endsection
