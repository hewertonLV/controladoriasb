@extends('layouts.app')

@section('title', 'Corrigir venda')
@section('page-title', 'Movimentação — Corrigir venda')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <h4 class="header-title mb-0">Corrigir item da venda {{ $movimentacao->vendaNota?->numero_nf ? 'NF '.$movimentacao->vendaNota->numero_nf : '#'.$movimentacao->id }}</h4>
            <a href="{{ route('admin.movimentacoes.vendas.show', $movimentacao) }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
        </div>
        <div class="card-body">
            <div class="alert alert-info">
                Esta venda possui <strong>{{ $itens->count() }}</strong> item(ns). Abaixo estão todas as frutas do mesmo lançamento; a correção aberta altera o item selecionado.
            </div>
            <div class="table-responsive mb-3">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fruta</th>
                            <th>Qtd UM</th>
                            <th>Qtd kg</th>
                            <th>Valor vendido</th>
                            <th>Custo</th>
                            <th>Resultado</th>
                            <th class="text-end">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($itens as $item)
                            <tr @class(['table-primary' => $item->is($movimentacao)])>
                                <td class="fw-semibold">{{ $item->fruta?->nome ?? '—' }}</td>
                                <td>{{ number_format((float) $item->qtd_fruta_um, 2, ',', '.') }} {{ $item->fruta?->unidade_medicao ?? '' }}</td>
                                <td>{{ number_format((float) $item->qtd_fruta_kg, 2, ',', '.') }}</td>
                                <td>R$ {{ number_format((float) $item->valor_nf_total, 2, ',', '.') }}</td>
                                <td>R$ {{ number_format((float) $item->valor_custo_saida, 2, ',', '.') }}</td>
                                <td>R$ {{ number_format((float) $item->resultado_movimentacao, 2, ',', '.') }}</td>
                                <td class="text-end">
                                    @if (! $item->is($movimentacao))
                                        <a href="{{ route('admin.movimentacoes.vendas.edit', $item) }}" class="btn btn-soft-primary btn-sm">Corrigir este item</a>
                                    @else
                                        <span class="badge bg-primary">Em edição</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('admin.movimentacoes.vendas._form-create', ['movimentacao' => $movimentacao, 'opcoes' => $opcoes])
        </div>
    </div>
@endsection

@push('scripts')
    @include('admin.movimentacoes.compras._masks')
@endpush
