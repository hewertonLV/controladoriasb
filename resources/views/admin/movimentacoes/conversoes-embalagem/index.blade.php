@extends('layouts.app')

@section('title', 'Conversões de embalagem')
@section('page-title', 'Movimentação — Conversão de embalagem')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center flex-wrap gap-2">
            <div>
                <h4 class="header-title mb-0">Conversões de embalagem</h4>
                <p class="text-muted mb-0 small">Movimentações de conversão entre embalagens/frutas com registro de perda.</p>
            </div>
            @can('movimentacoes.conversoes-embalagem.criar')
                <a href="{{ route('admin.movimentacoes.conversoes-embalagem.create') }}" class="btn btn-primary btn-sm ms-auto">
                    <i class="ri-add-line me-1"></i> Nova conversão
                </a>
            @endcan
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Unidade</th>
                            <th>Origem</th>
                            <th>Destino</th>
                            <th>Perda</th>
                            <th>Data</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movimentacoes as $m)
                            <tr>
                                <td>{{ $m->id }}</td>
                                <td>{{ $m->empresaOrigem?->nomeExibicao() }}</td>
                                <td>{{ $m->fruta?->nome }} — {{ number_format((float) $m->qtd_fruta_um, 2, ',', '.') }} UM</td>
                                <td>{{ $m->frutaDestinoConversao?->nome }} — {{ number_format((float) $m->qtd_resultante_um, 2, ',', '.') }} UM</td>
                                <td>{{ number_format((float) $m->qtd_perda_conversao_um, 2, ',', '.') }} UM / {{ number_format((float) $m->qtd_perda_conversao_kg, 2, ',', '.') }} kg</td>
                                <td>{{ $m->data_movimentacao?->format('d/m/Y H:i') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.movimentacoes.conversoes-embalagem.show', $m) }}" class="btn btn-light btn-sm" title="Ver">
                                        <i class="ri-eye-line"></i> Ver
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Nenhuma conversão registrada.</td>
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
