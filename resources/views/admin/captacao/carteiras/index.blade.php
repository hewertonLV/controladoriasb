@extends('layouts.app')

@section('title', 'Carteiras de captação')
@section('page-title', 'Carteiras de captação')

@section('content')
    <x-admin.flash-messages />

    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <p class="text-muted mb-0">Carteira = faturamento + galpão/estoque físico. Só é possível inativar sem lojas vinculadas.</p>
        @can('captacao.lote.visualizar')
            <a href="{{ route('admin.captacao.carteiras.create') }}" class="btn btn-sm btn-primary">
                <i class="ri-add-line me-1"></i> Nova carteira
            </a>
        @endcan
    </div>

    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link {{ $aba === 'ativas' ? 'active' : '' }}"
               href="{{ route('admin.captacao.carteiras.index', ['aba' => 'ativas']) }}">
                Carteiras ativas
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ $aba === 'inativas' ? 'active' : '' }}"
               href="{{ route('admin.captacao.carteiras.index', ['aba' => 'inativas']) }}">
                Carteiras inativas
            </a>
        </li>
    </ul>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                <tr>
                    <th>Nome</th>
                    <th>Faturamento</th>
                    <th>Galpão</th>
                    <th>Lojas vinculadas</th>
                    <th class="text-end">Ações</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($carteiras as $carteira)
                    <tr>
                        <td class="fw-semibold">{{ $carteira->nome }}</td>
                        <td>{{ $carteira->unidadeFaturamento?->nome }}</td>
                        <td>{{ $carteira->unidadeGalpao?->nome }}</td>
                        <td>{{ $carteira->clientes_count }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                <a href="{{ route('admin.captacao.carteiras.edit', $carteira) }}" class="btn btn-sm btn-light">
                                    <i class="ri-pencil-line"></i> Editar
                                </a>
                                @if ($somenteAtivas)
                                    <form method="post"
                                          action="{{ route('admin.captacao.carteiras.inativar', $carteira) }}"
                                          class="d-inline"
                                          data-confirm="Inativar a carteira «{{ $carteira->nome }}»?"
                                          data-confirm-title="Inativar carteira"
                                          data-confirm-variant="danger"
                                          data-confirm-btn="Inativar"
                                          @disabled($carteira->clientes_count > 0)>
                                        @csrf
                                        <button type="submit"
                                                class="btn btn-sm btn-soft-danger"
                                                @disabled($carteira->clientes_count > 0)
                                                title="{{ $carteira->clientes_count > 0 ? 'Remova o vínculo das lojas antes de inativar' : 'Inativar carteira' }}">
                                            <i class="ri-forbid-line"></i> Inativar
                                        </button>
                                    </form>
                                @else
                                    <form method="post"
                                          action="{{ route('admin.captacao.carteiras.reativar', $carteira) }}"
                                          class="d-inline"
                                          data-confirm="Reativar a carteira «{{ $carteira->nome }}»?"
                                          data-confirm-title="Reativar carteira"
                                          data-confirm-variant="success"
                                          data-confirm-btn="Reativar">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-soft-success">
                                            <i class="ri-check-line"></i> Reativar
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-muted text-center py-4">
                            @if ($somenteAtivas)
                                Nenhuma carteira ativa cadastrada.
                            @else
                                Nenhuma carteira inativa.
                            @endif
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
