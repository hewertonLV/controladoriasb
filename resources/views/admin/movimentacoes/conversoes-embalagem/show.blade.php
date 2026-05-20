@extends('layouts.app')

@section('title', 'Conversão de embalagem #'.$movimentacao->id)
@section('page-title', 'Movimentação — Conversão de embalagem')

@section('content')
    <x-admin.flash-messages />

    <div class="d-flex align-items-center mb-3">
        <h4 class="header-title mb-0">Conversão de embalagem #{{ $movimentacao->id }}</h4>
        <a href="{{ route('admin.movimentacoes.conversoes-embalagem.index') }}" class="btn btn-light btn-sm ms-auto">Lista</a>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">Saída da embalagem original</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Unidade</dt>
                        <dd class="col-sm-7">{{ $movimentacao->empresaOrigem?->nomeExibicao() }}</dd>
                        <dt class="col-sm-5">Fruta</dt>
                        <dd class="col-sm-7">{{ $movimentacao->fruta?->nome }}</dd>
                        <dt class="col-sm-5">Qtd original</dt>
                        <dd class="col-sm-7">{{ number_format((float) $movimentacao->qtd_fruta_um, 2, ',', '.') }} UM / {{ number_format((float) $movimentacao->qtd_fruta_kg, 2, ',', '.') }} kg</dd>
                        <dt class="col-sm-5">Preço médio kg</dt>
                        <dd class="col-sm-7">R$ {{ number_format((float) $movimentacao->preco_medio_fruta_kg, 2, ',', '.') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header fw-semibold">Entrada da fruta resultante</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5">Fruta resultante</dt>
                        <dd class="col-sm-7">{{ $movimentacao->frutaDestinoConversao?->nome }}</dd>
                        <dt class="col-sm-5">Qtd resultante</dt>
                        <dd class="col-sm-7">{{ number_format((float) $movimentacao->qtd_resultante_um, 2, ',', '.') }} UM / {{ number_format((float) $movimentacao->qtd_resultante_kg, 2, ',', '.') }} kg</dd>
                        <dt class="col-sm-5">Perda</dt>
                        <dd class="col-sm-7">{{ number_format((float) $movimentacao->qtd_perda_conversao_um, 2, ',', '.') }} UM da origem / {{ number_format((float) $movimentacao->qtd_perda_conversao_kg, 2, ',', '.') }} kg</dd>
                        <dt class="col-sm-5">Valor perda</dt>
                        <dd class="col-sm-7">R$ {{ number_format((float) $movimentacao->valor_perda_conversao, 2, ',', '.') }}</dd>
                        <dt class="col-sm-5">Entrada vinculada</dt>
                        <dd class="col-sm-7">#{{ $entrada?->id }}</dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
