@extends('layouts.app')

@section('title', 'Transferências')
@section('page-title', 'Movimentação — Transferência')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center flex-wrap gap-2">
            <div>
                <h4 class="header-title mb-0">Transferências</h4>
                <p class="text-muted mb-0 small">Saída na origem e entrada pendente no destino (versão ativa).</p>
            </div>
            @can('movimentacoes.transferencias.criar')
                <a href="{{ route('admin.movimentacoes.transferencias.create') }}" class="btn btn-primary btn-sm ms-auto">
                    <i class="ri-add-line me-1"></i> Nova transferência
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
                            <th>Status</th>
                            <th class="text-end">UM</th>
                            <th class="text-end">Kg</th>
                            <th>Frete</th>
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
                                <td><span class="badge bg-secondary">{{ $m->status_transferencia ?? '—' }}</span></td>
                                <td class="text-end">{{ number_format((float) $m->qtd_fruta_um, 2, ',', '.') }}</td>
                                <td class="text-end">{{ number_format((float) $m->qtd_fruta_kg, 2, ',', '.') }}</td>
                                <td>{{ $m->frete?->nome ?? '—' }}</td>
                                <td class="text-end">
                                    @can('movimentacoes.transferencias.visualizar')
                                        <a href="{{ route('admin.movimentacoes.transferencias.show', ['transferenciaOrigem' => $m->transferencia_origem_id]) }}"
                                           class="btn btn-light btn-sm"
                                           title="Ver">
                                            <i class="ri-eye-line"></i> Ver
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">Nenhuma transferência registrada.</td>
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
