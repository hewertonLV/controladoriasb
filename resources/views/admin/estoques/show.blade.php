@extends('layouts.app')

@section('title', 'Estoque — '.$estoque->unidadeNegocio->nome.' / '.$estoque->fruta->nome)
@section('page-title', 'Detalhe do estoque')

@section('content')
    <x-admin.flash-messages />

    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <h4 class="header-title mb-0">{{ $estoque->unidadeNegocio->nome }} · {{ $estoque->fruta->nome }}</h4>
                <p class="text-muted mb-0">
                    Unidade CIGAM <code>{{ $estoque->unidadeNegocio->id_cigam }}</code>
                    · Fruta CIGAM <code>{{ $estoque->fruta->id_cigam }}</code>
                    · {{ (float) $estoque->fruta->kg_por_unidade_medicao }} kg/{{ $estoque->fruta->unidade_medicao }}
                </p>
            </div>
            @can('estoques.movimentar')
                <a href="{{ route('admin.estoques.movimentar', ['id_unidade_negocio' => $estoque->id_unidade_negocio, 'id_fruta' => $estoque->id_fruta]) }}"
                   class="btn btn-primary btn-sm">
                    <i class="ri-exchange-line me-1"></i> Nova movimentação
                </a>
            @endcan
            <a href="{{ route('admin.estoques.index') }}" class="btn btn-light btn-sm">Voltar</a>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="text-muted small">Quantidade (kg)</div>
                    <div class="fs-18 fw-semibold">{{ number_format((float) $estoque->qtd_fruta_kg, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Quantidade ({{ $estoque->fruta->unidade_medicao }})</div>
                    <div class="fs-18 fw-semibold">{{ number_format((float) $estoque->qtd_fruta_um, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Preço médio (kg)</div>
                    <div class="fs-18 fw-semibold">R$ {{ number_format((float) $estoque->preco_medio_kg, 2, ',', '.') }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted small">Valor total acumulado</div>
                    <div class="fs-18 fw-semibold">R$ {{ number_format((float) $estoque->valor_total_acumulado, 2, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="header-title mb-0">Movimentações</h5>
        </div>
        <div class="table-responsive mb-0">
            <table class="table table-sm table-centered mb-0">
                <thead class="bg-light bg-opacity-50">
                    <tr>
                        <th>Data</th>
                        <th>Qtd kg</th>
                        <th>Qtd UM</th>
                        <th>Pço médio kg</th>
                        <th>Valor posição</th>
                        <th>Última?</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movimentacoes as $mov)
                        <tr class="{{ $mov->status_ultima_posicao ? 'bg-success-subtle' : '' }}">
                            <td>{{ optional($mov->created_at)->format('d/m/Y H:i:s') ?? '—' }}</td>
                            <td>{{ number_format((float) $mov->qtd_fruta_kg, 2, ',', '.') }}</td>
                            <td>{{ number_format((float) $mov->qtd_fruta_um, 2, ',', '.') }}</td>
                            <td>R$ {{ number_format((float) $mov->preco_medio_kg, 2, ',', '.') }}</td>
                            <td>R$ {{ number_format((float) $mov->valor_total_fruta, 2, ',', '.') }}</td>
                            <td>
                                @if ($mov->status_ultima_posicao)
                                    <span class="badge bg-success-subtle text-success">Sim</span>
                                @else
                                    <span class="badge bg-secondary-subtle text-secondary">Não</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">Sem movimentações registradas.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $movimentacoes->links() }}
        </div>
    </div>
@endsection
