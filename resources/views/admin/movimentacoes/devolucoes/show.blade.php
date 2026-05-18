@extends('layouts.app')

@section('title', 'Devolução')
@section('page-title', 'Movimentação — Devolução')

@section('content')
    <x-admin.flash-messages />

    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <h4 class="header-title mb-0">Devolução #{{ $movimentacao->id }}</h4>
            <a href="{{ route('admin.movimentacoes.devolucoes.index') }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
            @can('movimentacoes.devolucoes.editar')
                <a href="{{ route('admin.movimentacoes.devolucoes.edit', $movimentacao) }}" class="btn btn-primary btn-sm">Corrigir</a>
            @endcan
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3"><strong>NF devolução:</strong><br>{{ $movimentacao->numero_nf_devolucao }}</div>
                <div class="col-md-3"><strong>NF venda:</strong><br>{{ $movimentacao->vendaOrigem?->vendaNota?->numero_nf ?? '—' }}</div>
                <div class="col-md-3"><strong>Tipo:</strong><br>{{ str_replace('_', ' ', $movimentacao->tipo_devolucao ?? '') }}</div>
                <div class="col-md-3"><strong>Status:</strong><br>{{ $movimentacao->status_registro }}</div>
                <div class="col-md-4"><strong>Cliente:</strong><br>{{ $movimentacao->vendaOrigem?->empresaDestino?->nomeExibicao() ?? $movimentacao->empresaOrigem?->nomeExibicao() ?? '—' }}</div>
                <div class="col-md-4"><strong>Destino estoque:</strong><br>{{ $movimentacao->empresaDestino?->nomeExibicao() ?? '—' }}</div>
                <div class="col-md-4"><strong>Unidade retorno:</strong><br>{{ $movimentacao->unidadeRetorno?->nome ?? '—' }}</div>
                <div class="col-md-4"><strong>Fruta:</strong><br>{{ $movimentacao->fruta?->nome ?? '—' }}</div>
                <div class="col-md-3"><strong>Qtd UM:</strong><br>{{ number_format((float) $movimentacao->qtd_fruta_um, 2, ',', '.') }}</div>
                <div class="col-md-3"><strong>Qtd kg:</strong><br>{{ number_format((float) $movimentacao->qtd_fruta_kg, 2, ',', '.') }}</div>
                <div class="col-md-3"><strong>Valor devolvido:</strong><br>R$ {{ number_format((float) $movimentacao->valor_devolucao_total, 2, ',', '.') }}</div>
                <div class="col-md-3"><strong>Custo devolvido:</strong><br>R$ {{ number_format((float) $movimentacao->valor_custo_devolucao, 2, ',', '.') }}</div>
                <div class="col-md-3"><strong>Resultado estornado:</strong><br>R$ {{ number_format((float) $movimentacao->resultado_devolucao, 2, ',', '.') }}</div>
                <div class="col-md-9"><strong>Motivo:</strong><br>{{ $movimentacao->motivo_devolucao ?? '—' }}</div>
            </div>

            @can('movimentacoes.devolucoes.cancelar-admin')
                @if ($movimentacao->status_registro === \App\Enums\MovimentacaoStatusRegistro::ATIVO->value)
                    <hr>
                    <form method="POST" action="{{ route('admin.movimentacoes.devolucoes.cancelar-admin', $movimentacao) }}" class="row g-2">
                        @csrf
                        <div class="col-md-10">
                            <input name="motivo" class="form-control" required placeholder="Motivo do cancelamento administrativo">
                        </div>
                        <div class="col-md-2 d-grid">
                            <button class="btn btn-danger" type="submit">Cancelar devolução</button>
                        </div>
                    </form>
                @endif
            @endcan
        </div>
    </div>
@endsection
