@extends('layouts.app')

@section('title', 'Entradas de estoque')
@section('page-title', 'Movimentação — Entrada de estoque')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center flex-wrap gap-2">
            <div>
                <h4 class="header-title mb-0">Entradas de estoque</h4>
                <p class="text-muted mb-0 small">Produção interna — aumenta saldo e recalcula preço médio.</p>
            </div>
            @can('movimentacoes.entradas-estoque.criar')
                <a href="{{ route('admin.movimentacoes.entradas-estoque.create') }}" class="btn btn-primary btn-sm ms-auto">
                    <i class="ri-add-line me-1"></i> Nova entrada
                </a>
            @endcan
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Data</th>
                            <th>Unidade</th>
                            <th>Fruta</th>
                            <th class="text-end">Qtd UM</th>
                            <th class="text-end">Preço / UM</th>
                            <th class="text-end">Valor total</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movimentacoes as $m)
                            <tr>
                                <td>{{ $m->data_movimentacao?->format('d/m/Y H:i') }}</td>
                                <td>{{ $m->empresaOrigem?->nomeExibicao() ?? '—' }}</td>
                                <td>{{ $m->fruta?->nome ?? '—' }}</td>
                                <td class="text-end">{{ number_format((float) $m->qtd_fruta_um, 2, ',', '.') }}</td>
                                <td class="text-end">R$ {{ number_format((float) $m->valor_nf_um, 2, ',', '.') }}</td>
                                <td class="text-end">R$ {{ number_format((float) $m->valor_nf_total, 2, ',', '.') }}</td>
                                <td class="text-end">
                                    @can('movimentacoes.entradas-estoque.visualizar')
                                        <a href="{{ route('admin.movimentacoes.entradas-estoque.show', $m) }}" class="btn btn-light btn-sm">
                                            <i class="ri-eye-line"></i> Ver
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Nenhuma entrada de estoque registrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($movimentacoes->hasPages())
            <div class="card-footer">{{ $movimentacoes->links() }}</div>
        @endif
    </div>
@endsection
