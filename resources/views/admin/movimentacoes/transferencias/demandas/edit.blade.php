@extends('layouts.app')

@section('title', 'Demanda #' . $demanda->id)
@section('page-title', 'Movimentação — Demanda de transferência')

@section('content')
    <x-admin.flash-messages />

    @php
        $status = \App\Enums\TransferenciaDemandaStatus::tryFrom($demanda->status);
        $editavel = $status === \App\Enums\TransferenciaDemandaStatus::DemandaCriada;
    @endphp

    <div class="card mb-3">
        <div class="card-header d-flex align-items-center gap-2 flex-wrap">
            <h4 class="header-title mb-0">Demanda #{{ $demanda->id }}</h4>
            <span class="badge bg-light text-body-secondary border">{{ $status?->label() ?? $demanda->status }}</span>
            <a href="{{ route('admin.movimentacoes.transferencias.demandas.index') }}" class="btn btn-light btn-sm ms-auto">Voltar</a>
        </div>
        <div class="card-body">
            @if ($editavel)
                <form method="post" action="{{ route('admin.movimentacoes.transferencias.demandas.update', $demanda) }}">
                    @csrf
                    @method('PUT')
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Origem</label>
                            <select name="id_unidade_negocio_origem" class="form-select" required>
                                @foreach ($unidades as $unidade)
                                    <option value="{{ $unidade->id }}" @selected($demanda->id_unidade_negocio_origem == $unidade->id)>{{ $unidade->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Destino</label>
                            <select name="id_unidade_negocio_destino" class="form-select" required>
                                @foreach ($unidades as $unidade)
                                    <option value="{{ $unidade->id }}" @selected($demanda->id_unidade_negocio_destino == $unidade->id)>{{ $unidade->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observação</label>
                            <input type="text" name="observacao" class="form-control" maxlength="500" value="{{ old('observacao', $demanda->observacao) }}">
                        </div>
                    </div>

                    @include('admin.movimentacoes.transferencias.demandas._form-linhas')

                    <button type="submit" class="btn btn-primary">Salvar alterações</button>
                </form>
            @else
                <div class="row g-2 small mb-3">
                    <div class="col-md-6"><strong>Origem:</strong> {{ $demanda->unidadeOrigem?->nome }}</div>
                    <div class="col-md-6"><strong>Destino:</strong> {{ $demanda->unidadeDestino?->nome }}</div>
                    @foreach ($demanda->linhas as $linha)
                        <div class="col-12">{{ $linha->fruta?->nome }} — {{ rtrim(rtrim(number_format((float) $linha->qtd_um, 3, '.', ''), '0'), '.') }}</div>
                    @endforeach
                </div>
            @endif

            <div class="d-flex flex-wrap gap-2 mt-3">
                @if ($status === \App\Enums\TransferenciaDemandaStatus::DemandaCriada)
                    <form method="post" action="{{ route('admin.movimentacoes.transferencias.demandas.iniciar', $demanda) }}">
                        @csrf
                        <button type="submit" class="btn btn-soft-primary btn-sm">Iniciar transferência</button>
                    </form>
                    <form method="post" action="{{ route('admin.movimentacoes.transferencias.demandas.destroy', $demanda) }}" onsubmit="return confirm('Excluir demanda?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-soft-danger btn-sm">Excluir</button>
                    </form>
                @endif

                @if ($status === \App\Enums\TransferenciaDemandaStatus::Iniciado)
                    <form method="post" action="{{ route('admin.movimentacoes.transferencias.demandas.nf', $demanda) }}" enctype="multipart/form-data" class="d-flex align-items-center gap-2">
                        @csrf
                        <input type="file" name="arquivo_nf" class="form-control form-control-sm" required accept=".xml,.pdf,.txt">
                        <button type="submit" class="btn btn-soft-success btn-sm text-nowrap">Anexar NF</button>
                    </form>
                @endif

                @if ($status === \App\Enums\TransferenciaDemandaStatus::VincularFrete)
                    <form method="post" action="{{ route('admin.movimentacoes.transferencias.demandas.concluir-frete', $demanda) }}" class="w-100">
                        @csrf
                        <div class="row g-2 align-items-end">
                            <div class="col-md-8">
                                <label class="form-label small mb-0">Frete (opcional)</label>
                                <select name="id_frete" class="form-select form-select-sm">
                                    <option value="">Selecione um frete aberto</option>
                                    @foreach ($fretesAbertos as $frete)
                                        <option value="{{ $frete->id }}">#{{ $frete->id }} — {{ $frete->descricao ?? 'Frete' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="sem_frete" value="1" id="sem-frete-demanda">
                                    <label class="form-check-label small" for="sem-frete-demanda">Sem frete</label>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm w-100">Concluir demanda</button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection
