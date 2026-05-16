@extends('layouts.app')

@section('title', 'Descartes')
@section('page-title', 'Movimentação — Descarte')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center flex-wrap gap-2">
            <div>
                <h4 class="header-title mb-0">Descartes</h4>
                <p class="text-muted mb-0 small">Saídas por perda operacional com custo médio preservado (versão ativa).</p>
            </div>
            @can('movimentacoes.descartes.criar')
                <a href="{{ route('admin.movimentacoes.descartes.create') }}" class="btn btn-primary btn-sm ms-auto">
                    <i class="ri-add-line me-1"></i> Novo descarte
                </a>
            @endcan
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Data</th>
                            <th>Unidade (origem)</th>
                            <th>Fruta</th>
                            <th>Categoria</th>
                            <th class="text-end">Qtd (UM)</th>
                            <th class="text-end">Qtd (kg)</th>
                            <th class="text-end">Valor econômico</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movimentacoes as $m)
                            <tr>
                                <td>{{ $m->data_movimentacao?->format('d/m/Y H:i') }}</td>
                                <td>{{ $m->empresaOrigem?->nomeExibicao() ?? '—' }}</td>
                                <td>{{ $m->fruta?->nome ?? '—' }}</td>
                                <td>{{ $m->categoriaDescarte?->nome ?? '—' }}</td>
                                <td class="text-end">{{ number_format((float) $m->qtd_fruta_um, 2, ',', '.') }}</td>
                                <td class="text-end">{{ number_format((float) $m->qtd_fruta_kg, 2, ',', '.') }}</td>
                                <td class="text-end">R$ {{ number_format($m->valorEconomicoParaRelatorio(), 2, ',', '.') }}</td>
                                <td>{{ $m->status_registro }}</td>
                                <td class="text-end">
                                    @can('movimentacoes.descartes.visualizar')
                                        <a href="{{ route('admin.movimentacoes.descartes.show', $m) }}" class="btn btn-light btn-sm">Ver</a>
                                    @endcan
                                    @can('movimentacoes.descartes.editar')
                                        <a href="{{ route('admin.movimentacoes.descartes.edit', $m) }}" class="btn btn-light btn-sm">Editar</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">Nenhum descarte registrado.</td>
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
