@extends('layouts.app')

@section('title', 'Alertas comerciais')
@section('page-title', 'Alertas comerciais')

@section('content')
    <x-admin.flash-messages />

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Data</label>
                    <input type="date" name="data_referencia" class="form-control" value="{{ $dataReferencia }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Faturamento</label>
                    <select name="id_unidade_negocio_faturamento" class="form-select" required>
                        <option value="">—</option>
                        @foreach ($faturamentos as $un)
                            <option value="{{ $un->id }}" @selected($idFaturamento === $un->id)>{{ $un->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Galpão (opcional)</label>
                    <select name="id_unidade_negocio_galpao" class="form-select">
                        <option value="">Todos</option>
                        @foreach ($galpoes as $un)
                            <option value="{{ $un->id }}" @selected($idGalpao === $un->id)>{{ $un->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Consultar</button>
                </div>
            </form>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-lojas" type="button">Lojas sem pedido</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-frutas" type="button">Frutas faltantes</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-lojas">
            <div class="card"><div class="card-body table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Loja</th><th>Ocorrências (4 sem.)</th></tr></thead>
                    <tbody>
                    @forelse ($lojasSemPedido as $linha)
                        <tr><td>{{ $linha['cliente_nome'] }}</td><td>{{ $linha['ocorrencias'] }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-muted">Nenhum alerta.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div></div>
        </div>
        <div class="tab-pane fade" id="tab-frutas">
            <div class="card"><div class="card-body table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Loja</th><th>Fruta</th></tr></thead>
                    <tbody>
                    @forelse ($frutasFaltantes as $linha)
                        <tr><td>{{ $linha['cliente_nome'] }}</td><td>{{ $linha['fruta_nome'] }}</td></tr>
                    @empty
                        <tr><td colspan="2" class="text-muted">Nenhum alerta.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div></div>
        </div>
    </div>
@endsection
