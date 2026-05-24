@extends('layouts.app')

@section('title', 'Captação — Lotes')
@section('page-title', 'Captação — Lotes')

@section('content')
    <x-admin.flash-messages />

    @canany(['captacao.cliente_fruta.vincular', 'captacao.pedido.editar', 'captacao.lote.visualizar'])
        <div class="alert alert-info d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
            <span>Antes da matriz, vincule quais frutas cada loja compra.</span>
            <a href="{{ route('admin.captacao.frutas-por-loja.index') }}" class="btn btn-sm btn-primary">
                <i class="ri-apple-line me-1"></i> Frutas por loja
            </a>
        </div>
    @endcanany

    <div class="card mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Data</label>
                    <input type="date" name="data_referencia" class="form-control" value="{{ $filtros['data_referencia'] ?? '' }}">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

  <div class="card mb-3">
        <div class="card-header"><strong>Abrir lote do dia</strong></div>
        <div class="card-body">
            <form method="post" action="{{ route('admin.captacao.lotes.store') }}" class="row g-2">
                @csrf
                <div class="col-md-2">
                    <label class="form-label">Data</label>
                    <input type="date" name="data_referencia" class="form-control" value="{{ old('data_referencia', now()->toDateString()) }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Faturamento</label>
                    <select name="id_unidade_negocio_faturamento" class="form-select" required>
                        @foreach ($faturamentos as $un)
                            <option value="{{ $un->id }}">{{ $un->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Galpão</label>
                    <select name="id_unidade_negocio_galpao" class="form-select" required>
                        @foreach ($galpoes as $un)
                            <option value="{{ $un->id }}">{{ $un->nome }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-success w-100">Abrir</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                <tr>
                    <th>Data</th>
                    <th>Faturamento</th>
                    <th>Galpão</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($lotes as $lote)
                    <tr>
                        <td>{{ $lote->data_referencia->format('d/m/Y') }}</td>
                        <td>{{ $lote->unidadeFaturamento->nome }}</td>
                        <td>{{ $lote->unidadeGalpao->nome }}</td>
                        <td>{{ $lote->status->label() }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                @can('captacao.lote.visualizar')
                                    <a href="{{ route('admin.captacao.lotes.show', $lote) }}"
                                       class="btn btn-light btn-sm"
                                       title="Detalhes, romaneios e pipeline">
                                        <i class="ri-eye-line"></i> Ver
                                    </a>
                                    <a href="{{ route('admin.captacao.matriz.index', ['lote' => $lote->id]) }}"
                                       class="btn btn-light btn-sm"
                                       title="Editar pedidos na matriz">
                                        <i class="ri-pencil-line"></i> Editar
                                    </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">Nenhum lote encontrado.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $lotes->links() }}
        </div>
    </div>
@endsection
