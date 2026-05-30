@extends('layouts.app')

@section('title', 'Demandas de transferência')
@section('page-title', 'Movimentação — Demandas de transferência')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2 flex-wrap">
            <h4 class="header-title mb-0">Demandas manuais</h4>
            <div class="ms-auto d-flex gap-2">
                <a href="{{ route('admin.movimentacoes.transferencias.index') }}" class="btn btn-light btn-sm">Transferências</a>
                @can('movimentacoes.transferencias.criar')
                    <a href="{{ route('admin.movimentacoes.transferencias.demandas.create') }}" class="btn btn-primary btn-sm">
                        <i class="ri-add-line me-1"></i> Nova demanda
                    </a>
                @endcan
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Origem</th>
                        <th>Destino</th>
                        <th>Status</th>
                        <th>Frutas</th>
                        <th class="text-end">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($demandas as $demanda)
                        <tr>
                            <td>{{ $demanda->id }}</td>
                            <td>{{ $demanda->unidadeOrigem?->nome ?? '—' }}</td>
                            <td>{{ $demanda->unidadeDestino?->nome ?? '—' }}</td>
                            <td>
                                <span class="badge bg-light text-body-secondary border">
                                    {{ \App\Enums\TransferenciaDemandaStatus::tryFrom($demanda->status)?->label() ?? $demanda->status }}
                                </span>
                            </td>
                            <td class="small">
                                @foreach ($demanda->linhas as $linha)
                                    <div>{{ $linha->fruta?->nome }} — {{ rtrim(rtrim(number_format((float) $linha->qtd_um, 3, '.', ''), '0'), '.') }}</div>
                                @endforeach
                            </td>
                            <td class="text-end">
                                <a href="{{ route('admin.movimentacoes.transferencias.demandas.edit', $demanda) }}" class="btn btn-soft-primary btn-sm">Abrir</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Nenhuma demanda manual cadastrada.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
