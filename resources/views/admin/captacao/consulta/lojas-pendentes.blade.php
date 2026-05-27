@extends('layouts.app')

@section('title', 'Lojas pendentes')
@section('page-title', 'Lojas pendentes')

@section('content')
    <x-admin.flash-messages />

    <div class="alert alert-secondary">
        Lista as lojas com <strong>programação de criação de pedido</strong> no dia da semana da data consultada
        (<strong>{{ $diaSemanaLabel }}</strong>).
        Destaca quem já <strong>iniciou</strong> pedido na captação do dia e quem está <strong>pendente</strong>.
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
                    <select name="id_captacao_carteira"
                            class="form-select"
                            data-search-select
                            data-placeholder="Todas ou pesquise uma carteira">
                        <option value="" @selected($idCarteira === 0)>Todas as carteiras (meu vínculo)</option>
                        @foreach ($carteiras as $carteira)
                            <option value="{{ $carteira->id }}" @selected($idCarteira === $carteira->id)>
                                {{ $carteira->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Consultar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-2 mb-3">
            <div class="col-md-4">
                <div class="card border-0 bg-light">
                    <div class="card-body py-2">
                        <span class="text-muted small d-block">Programadas no dia</span>
                        <strong class="fs-5">{{ $totais['programadas'] }}</strong>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 bg-warning-subtle">
                    <div class="card-body py-2">
                        <span class="text-muted small d-block">Pendentes (não iniciou)</span>
                        <strong class="fs-5 text-warning">{{ $totais['pendentes'] }}</strong>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 bg-success-subtle">
                    <div class="card-body py-2">
                        <span class="text-muted small d-block">Já iniciou captação</span>
                        <strong class="fs-5 text-success">{{ $totais['iniciadas'] }}</strong>
                    </div>
                </div>
            </div>
        </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <strong>Lojas — {{ $diaSemanaLabel }} ({{ \Illuminate\Support\Carbon::parse($dataReferencia)->format('d/m/Y') }})</strong>
            <div class="d-flex flex-wrap gap-2 small">
                <span class="badge bg-warning-subtle text-warning">Pendente</span>
                <span class="badge bg-success-subtle text-success">Pedido iniciado</span>
            </div>
        </div>
        <div class="card-body table-responsive p-0">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>Status</th>
                    <th>Loja</th>
                    @if ($idCarteira === 0)
                        <th>Carteira</th>
                    @endif
                    <th>Dias criação</th>
                    <th>Dias envio</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($linhas as $linha)
                    <tr @class([
                        'table-warning' => $linha['status'] === 'pendente',
                        'table-success' => $linha['status'] === 'iniciou',
                    ])>
                        <td>
                            @if ($linha['status'] === 'pendente')
                                <span class="badge bg-warning text-dark">Pendente</span>
                            @elseif ($linha['captacao_concluida'])
                                <span class="badge bg-success">Concluída</span>
                            @else
                                <span class="badge bg-success">Iniciou</span>
                            @endif
                        </td>
                        <td class="fw-semibold">{{ $linha['cliente_nome'] }}</td>
                        @if ($idCarteira === 0)
                            <td>{{ $linha['carteira_nome'] }}</td>
                        @endif
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
                        <td colspan="{{ $idCarteira === 0 ? 5 : 4 }}" class="text-muted py-4 text-center">
                            @if ($carteiras->isEmpty())
                                Nenhuma carteira ativa disponível para o seu vínculo de unidades.
                            @else
                                Nenhuma loja com programação de criação de pedido para {{ $diaSemanaLabel }} na seleção.
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

@include('admin.captacao._search-select-scripts')
