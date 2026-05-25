@extends('layouts.app')

@section('title', 'Lojas sem pedido')
@section('page-title', 'Consulta — lojas sem pedido criado')

@section('content')
    <x-admin.flash-messages />

    <div class="alert alert-secondary">
        Somente consulta: lista clientes da carteira que ainda <strong>não têm pedido</strong> no lote de captação em andamento da data.
        Para padrão histórico de dias da semana, use <a href="{{ route('admin.captacao.alertas.index') }}">Alertas comerciais</a>.
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">Data</label>
                    <input type="date" name="data_referencia" class="form-control" value="{{ $dataReferencia }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Carteira</label>
                    <select name="id_captacao_carteira" class="form-select" required>
                        <option value="">Selecione…</option>
                        @foreach ($carteiras as $carteira)
                            <option value="{{ $carteira->id }}" @selected($idCarteira === $carteira->id)>{{ $carteira->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Consultar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Clientes sem pedido na data</strong></div>
        <div class="card-body table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Dias criação do pedido</th>
                    <th>Dias envio</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($clientesSemPedido as $linha)
                    <tr>
                        <td class="fw-semibold">{{ $linha['cliente_nome'] }}</td>
                        <td>
                            @if ($linha['dias_criacao_labels'] !== [])
                                {{ implode(', ', $linha['dias_criacao_labels']) }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($linha['dias_envio_labels'] !== [])
                                {{ implode(', ', $linha['dias_envio_labels']) }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-muted py-4 text-center">
                            @if ($idCarteira > 0)
                                Todos os clientes da carteira já têm pedido criado nesta data — ou não há clientes vinculados à carteira.
                            @else
                                Selecione data e carteira para consultar.
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
