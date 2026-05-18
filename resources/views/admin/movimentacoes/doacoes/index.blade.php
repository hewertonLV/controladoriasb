@extends('layouts.app')

@section('title', 'Doações')
@section('page-title', 'Movimentação — Doação')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center flex-wrap gap-2">
            <div>
                <h4 class="header-title mb-0">Doações</h4>
                <p class="text-muted mb-0 small">Saídas de estoque com custo médio preservado (versão ativa).</p>
            </div>
            @can('movimentacoes.doacoes.criar')
                <a href="{{ route('admin.movimentacoes.doacoes.create') }}" class="btn btn-primary btn-sm ms-auto">
                    <i class="ri-add-line me-1"></i> Nova doação
                </a>
            @endcan
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Data</th>
                            <th>Origem</th>
                            <th>Destino</th>
                            <th>Fruta</th>
                            <th class="text-end">UM</th>
                            <th class="text-end">Kg</th>
                            <th class="text-end">Baixa</th>
                            <th>Motivo</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movimentacoes as $m)
                            <tr>
                                <td>{{ $m->data_movimentacao?->format('d/m/Y H:i') }}</td>
                                <td>{{ $m->empresaOrigem?->nomeExibicao() ?? '—' }}</td>
                                <td>{{ $m->empresaDestino?->nomeExibicao() ?? '—' }}</td>
                                <td>{{ $m->fruta?->nome ?? '—' }}</td>
                                <td class="text-end">{{ number_format((float) $m->qtd_fruta_um, 2, ',', '.') }}</td>
                                <td class="text-end">{{ number_format((float) $m->qtd_fruta_kg, 2, ',', '.') }}</td>
                                <td class="text-end">R$ {{ number_format($m->valorEconomicoParaRelatorio(), 2, ',', '.') }}</td>
                                <td class="text-truncate" style="max-width: 12rem">{{ $m->motivo_doacao ?? '—' }}</td>
                                <td class="text-end">
                                    @can('movimentacoes.doacoes.visualizar')
                                        <a href="{{ route('admin.movimentacoes.doacoes.show', $m) }}" class="btn btn-light btn-sm" title="Ver">
                                            <i class="ri-eye-line"></i> Ver
                                        </a>
                                    @endcan
                                    @can('movimentacoes.doacoes.editar')
                                        <a href="{{ route('admin.movimentacoes.doacoes.edit', $m) }}" class="btn btn-light btn-sm" title="Editar">
                                            <i class="ri-pencil-line"></i> Editar
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">Nenhuma doação registrada.</td>
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
