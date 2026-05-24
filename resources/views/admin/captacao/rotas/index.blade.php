@extends('layouts.app')

@section('title', 'Rotas de captação')
@section('page-title', 'Rotas de captação')

@section('content')
    <x-admin.flash-messages />

    <div class="card mb-3">
        <div class="card-body">
            <p class="text-muted mb-3">
                Rotas de carregamento por galpão. Vincule cada pedido/loja a uma rota antes de finalizar a captação (Romaneio 1).
            </p>
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Galpão</label>
                    <select name="galpao" class="form-select" onchange="this.form.submit()">
                        <option value="">Todos os galpões</option>
                        @foreach ($galpoes as $galpao)
                            <option value="{{ $galpao->id }}" @selected((int) $galpaoId === $galpao->id)>{{ $galpao->nome }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <strong>Rotas cadastradas</strong>
            @can('captacao.rota.editar')
                <a href="{{ route('admin.captacao.rotas.create', $galpaoId ? ['galpao' => $galpaoId] : []) }}" class="btn btn-sm btn-primary">
                    <i class="ri-add-line me-1"></i> Nova rota
                </a>
            @endcan
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>Nome</th>
                    <th>Galpão</th>
                    <th>Veículo</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($rotas as $rota)
                    <tr>
                        <td class="fw-semibold">{{ $rota->nome }}</td>
                        <td>{{ $rota->unidadeGalpao?->nome ?? '—' }}</td>
                        <td>
                            @if ($rota->veiculo)
                                {{ $rota->veiculo->nome }}
                                <span class="text-muted small">(SBS {{ $rota->veiculo->id_sbs }})</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($rota->ativo)
                                <span class="badge bg-success">Ativa</span>
                            @else
                                <span class="badge bg-secondary">Inativa</span>
                            @endif
                        </td>
                        <td class="text-end">
                            @can('captacao.rota.editar')
                                <a href="{{ route('admin.captacao.rotas.edit', $rota) }}" class="btn btn-sm btn-light">
                                    <i class="ri-pencil-line"></i> Editar
                                </a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-muted py-4 text-center">
                            Nenhuma rota cadastrada.
                            @can('captacao.rota.editar')
                                <a href="{{ route('admin.captacao.rotas.create', $galpaoId ? ['galpao' => $galpaoId] : []) }}">Cadastrar primeira rota</a>
                            @endcan
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
