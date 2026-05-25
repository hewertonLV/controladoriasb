@extends('layouts.app')

@section('title', 'Frutas por loja')
@section('page-title', 'Frutas por loja')

@section('content')
    <x-admin.flash-messages />

    <div class="card mb-3">
        <div class="card-body">
            <p class="text-muted mb-3">
                Defina quais frutas cada loja compra. A matriz de captação monta as <strong>colunas</strong> com a união das frutas vinculadas às lojas do lote.
            </p>
            <form method="get" action="{{ route('admin.captacao.frutas-por-loja.index') }}" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Unidade de faturamento</label>
                    <select name="faturamento" class="form-select" onchange="this.form.submit()">
                        @foreach ($faturamentos as $un)
                            <option value="{{ $un->id }}" @selected((int) $faturamentoId === $un->id)>{{ $un->nome }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    </div>

    @if ($faturamentos->isEmpty())
        <div class="alert alert-warning">
            Nenhuma unidade de faturamento cadastrada (emite NF e não é hub). Cadastre em Unidades de negócio para vincular frutas às lojas.
        </div>
    @else
        <div class="card">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <strong>Lojas do faturamento</strong>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="text-muted small">{{ $clientes->count() }} loja(s)</span>
                    @can('captacao.cliente_fruta.vincular')
                        <a href="{{ route('admin.captacao.frutas-por-loja.importar', ['faturamento' => $faturamentoId]) }}"
                           class="btn btn-sm btn-soft-success">
                            <i class="ri-file-excel-2-line me-1"></i> Importar vínculos
                        </a>
                    @endcan
                </div>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Loja</th>
                        <th>Frutas vinculadas</th>
                        <th class="text-end text-nowrap">Ações</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($clientes as $cliente)
                        @php
                            $nomesFrutas = $cliente->frutaVinculos
                                ->map(fn ($v) => $v->fruta?->nome)
                                ->filter()
                                ->values();
                        @endphp
                        <tr>
                            <td>{{ $cliente->fantasia ?: $cliente->razao_social }}</td>
                            <td>
                                @if ($cliente->qtd_frutas > 0)
                                    <span class="badge bg-success me-1">{{ $cliente->qtd_frutas }}</span>
                                    <span class="text-muted small">{{ $nomesFrutas->take(5)->join(', ') }}{{ $nomesFrutas->count() > 5 ? '…' : '' }}</span>
                                @else
                                    <span class="badge bg-warning text-dark">Nenhuma</span>
                                    <span class="text-muted small">— configure antes da matriz</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                @canany(['captacao.cliente_fruta.vincular', 'captacao.pedido.editar', 'captacao.lote.visualizar'])
                                    <a href="{{ route('admin.captacao.frutas-por-loja.show', $cliente) }}"
                                       class="btn btn-sm btn-light"
                                       title="{{ ($podeSalvarVinculos ?? false) ? 'Abrir detalhe e vincular frutas' : 'Abrir detalhe (somente consulta)' }}">
                                        <i class="ri-file-list-3-line"></i> Detalhe
                                    </a>
                                @endcanany
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-muted py-4">
                                Nenhum cliente nesta unidade de faturamento.
                                @can('clientes.criar')
                                    <a href="{{ route('admin.clientes.create') }}" class="ms-1">Cadastrar cliente</a>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
