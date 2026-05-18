@extends('layouts.app')

@section('title', 'Venda')
@section('page-title', 'Movimentação — Venda')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <h4 class="header-title mb-0">Venda #{{ $movimentacao->id }}</h4>
            <a href="{{ route('admin.movimentacoes.vendas.index') }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
            @can('movimentacoes.vendas.editar')
                <a href="{{ route('admin.movimentacoes.vendas.edit', $movimentacao) }}" class="btn btn-primary btn-sm">Corrigir</a>
            @endcan
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><strong>NF:</strong><br>{{ $movimentacao->vendaNota?->numero_nf ?? '—' }}</div>
                <div class="col-md-3"><strong>Status:</strong><br>{{ $movimentacao->status_registro }}</div>
                <div class="col-md-3"><strong>Data:</strong><br>{{ $movimentacao->data_movimentacao?->format('d/m/Y H:i') }}</div>
                <div class="col-md-3"><strong>Versão:</strong><br>{{ $movimentacao->versao }}</div>
                <div class="col-md-4"><strong>Origem:</strong><br>{{ $movimentacao->empresaOrigem?->nomeExibicao() ?? '—' }}</div>
                <div class="col-md-4"><strong>Cliente:</strong><br>{{ $movimentacao->empresaDestino?->nomeExibicao() ?? '—' }}</div>
                <div class="col-md-4"><strong>Faturamento:</strong><br>{{ $movimentacao->unidadeFaturamento?->nome ?? '—' }}</div>
                <div class="col-md-3"><strong>Fruta:</strong><br>{{ $movimentacao->fruta?->nome ?? '—' }}</div>
                <div class="col-md-3"><strong>Qtd UM:</strong><br>{{ number_format((float) $movimentacao->qtd_fruta_um, 2, ',', '.') }}</div>
                <div class="col-md-3"><strong>Qtd kg:</strong><br>{{ number_format((float) $movimentacao->qtd_fruta_kg, 2, ',', '.') }}</div>
                <div class="col-md-3"><strong>Preço médio kg:</strong><br>R$ {{ number_format((float) $movimentacao->preco_medio_fruta_kg, 2, ',', '.') }}</div>
                <div class="col-md-3"><strong>Valor vendido:</strong><br>R$ {{ number_format((float) $movimentacao->valor_nf_total, 2, ',', '.') }}</div>
                <div class="col-md-3"><strong>Custo saída:</strong><br>R$ {{ number_format((float) $movimentacao->valor_custo_saida, 2, ',', '.') }}</div>
                <div class="col-md-3"><strong>Frete rateio:</strong><br>R$ {{ number_format((float) $movimentacao->valor_frete_rateio, 2, ',', '.') }}</div>
                <div class="col-md-3"><strong>Resultado:</strong><br>R$ {{ number_format((float) $movimentacao->resultado_movimentacao, 2, ',', '.') }}</div>
            </div>

            @can('movimentacoes.vendas.cancelar-admin')
                @if ($movimentacao->status_registro === \App\Enums\MovimentacaoStatusRegistro::ATIVO->value)
                    <hr>
                    <form method="POST" action="{{ route('admin.movimentacoes.vendas.cancelar-admin', $movimentacao) }}" class="row g-2">
                        @csrf
                        <div class="col-md-10">
                            <input name="motivo" class="form-control" required placeholder="Motivo do cancelamento administrativo">
                        </div>
                        <div class="col-md-2 d-grid">
                            <button class="btn btn-danger" type="submit">Cancelar venda</button>
                        </div>
                    </form>
                @endif
            @endcan
        </div>
    </div>
@endsection
