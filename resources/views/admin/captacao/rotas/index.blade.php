@extends('layouts.app')

@section('title', 'Rotas de captação')
@section('page-title', 'Rotas de captação')

@section('content')
    <x-admin.flash-messages />

    <div class="card mb-3">
        <div class="card-body">
            <p class="text-muted mb-3">
                Rotas de carregamento por carteira. Vincule cada pedido/loja a uma rota da mesma carteira do lote na matriz (abas Rotas e Por rota). A exigência de rota completa vale ao finalizar as vendas, não ao encerrar a captação.
            </p>
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Carteira</label>
                    <select name="carteira" class="form-select" onchange="this.form.submit()">
                        <option value="">Todas as carteiras</option>
                        @foreach ($carteiras as $carteira)
                            <option value="{{ $carteira->id }}" @selected((int) $carteiraId === $carteira->id)>
                                {{ $carteira->nome }}
                                — {{ $carteira->unidadeFaturamento?->nome }}
                                / {{ $carteira->unidadeGalpao?->nome }}
                            </option>
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
                <a href="{{ route('admin.captacao.rotas.create', $carteiraId ? ['carteira' => $carteiraId] : []) }}" class="btn btn-sm btn-primary">
                    <i class="ri-add-line me-1"></i> Nova rota
                </a>
            @endcan
        </div>
        <div class="card-body table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>Nome</th>
                    <th>Carteira</th>
                    <th>Faturamento</th>
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
                        <td>{{ $rota->carteira?->nome ?? '—' }}</td>
                        <td>{{ $rota->carteira?->unidadeFaturamento?->nome ?? '—' }}</td>
                        <td>{{ $rota->carteira?->unidadeGalpao?->nome ?? '—' }}</td>
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
                        <td colspan="7" class="text-muted py-4 text-center">
                            Nenhuma rota cadastrada.
                            @can('captacao.rota.editar')
                                <a href="{{ route('admin.captacao.rotas.create', $carteiraId ? ['carteira' => $carteiraId] : []) }}">Cadastrar primeira rota</a>
                            @endcan
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
