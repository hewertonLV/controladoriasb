@extends('layouts.app')

@section('title', 'Captação')
@section('page-title', 'Captação')

@push('head')
    <style>
        #captacao-lotes-table .captacao-lote-row {
            --captacao-lote-row-bg: transparent;
            --captacao-lote-row-border: var(--bs-border-color);
            background-color: var(--captacao-lote-row-bg) !important;
            box-shadow: inset 3px 0 0 var(--captacao-lote-row-border);
        }

        #captacao-lotes-table .captacao-lote-row--captacao {
            --captacao-lote-row-bg: rgba(var(--bs-primary-rgb), 0.1);
            --captacao-lote-row-border: var(--bs-primary);
        }

        #captacao-lotes-table .captacao-lote-row--aguardando {
            --captacao-lote-row-bg: rgba(var(--bs-warning-rgb), 0.12);
            --captacao-lote-row-border: var(--bs-warning);
        }

        #captacao-lotes-table .captacao-lote-row--andamento {
            --captacao-lote-row-bg: rgba(var(--bs-info-rgb), 0.1);
            --captacao-lote-row-border: var(--bs-info);
        }

        #captacao-lotes-table .captacao-lote-row--transferencia {
            --captacao-lote-row-bg: rgba(var(--bs-secondary-rgb), 0.12);
            --captacao-lote-row-border: var(--bs-secondary);
        }

        #captacao-lotes-table .captacao-lote-row--finalizado {
            --captacao-lote-row-bg: rgba(var(--bs-success-rgb), 0.1);
            --captacao-lote-row-border: var(--bs-success);
        }

        [data-bs-theme='dark'] #captacao-lotes-table .captacao-lote-row--captacao {
            --captacao-lote-row-bg: rgba(var(--bs-primary-rgb), 0.18);
        }

        [data-bs-theme='dark'] #captacao-lotes-table .captacao-lote-row--aguardando {
            --captacao-lote-row-bg: rgba(var(--bs-warning-rgb), 0.16);
        }

        [data-bs-theme='dark'] #captacao-lotes-table .captacao-lote-row--andamento {
            --captacao-lote-row-bg: rgba(var(--bs-info-rgb), 0.16);
        }

        [data-bs-theme='dark'] #captacao-lotes-table .captacao-lote-row--transferencia {
            --captacao-lote-row-bg: rgba(var(--bs-secondary-rgb), 0.2);
        }

        [data-bs-theme='dark'] #captacao-lotes-table .captacao-lote-row--finalizado {
            --captacao-lote-row-bg: rgba(var(--bs-success-rgb), 0.16);
        }
    </style>
@endpush

@section('content')
    <x-admin.flash-messages />

    <div class="card mb-3">
        <div class="card-header pb-0"><strong>Abrir captação do dia</strong></div>
        <div class="card-body pt-2">
            <p class="text-muted small mb-2">
                Só não é possível abrir outra captação se já existir uma <strong>em andamento</strong> na mesma carteira e data.
                Em qualquer outro status do lote anterior, você pode criar uma nova (complementar).
            </p>
            <form method="post" action="{{ route('admin.captacao.lotes.store') }}" class="row g-2">
                @csrf
                <div class="col-md-2">
                    <label class="form-label">Data</label>
                    <input type="date" name="data_referencia" class="form-control" value="{{ old('data_referencia', now()->toDateString()) }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Carteira</label>
                    <select name="id_captacao_carteira"
                            class="form-select"
                            data-search-select
                            data-placeholder="Selecione ou pesquise a carteira"
                            required>
                        <option value="">Selecione a carteira…</option>
                        @foreach ($carteiras as $carteira)
                            <option value="{{ $carteira->id }}" @selected((int) old('id_captacao_carteira') === $carteira->id)>
                                {{ $carteira->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100">Criar</button>
                </div>
            </form>
            
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table id="captacao-lotes-table" class="table table-sm align-middle">
                <thead>
                <tr>
                    <th>Data</th>
                    <th>Carteira</th>
                    <th>Faturamento</th>
                    <th>Galpão</th>
                    <th>Status</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse ($lotes as $lote)
                    <tr class="{{ $lote->status->classeLinhaListagem() }}">
                        <td>{{ $lote->data_referencia->format('d/m/Y') }}</td>
                        <td>{{ $lote->carteira?->nome ?? '—' }}</td>
                        <td>{{ $lote->unidadeFaturamento->nome }}</td>
                        <td>{{ $lote->unidadeGalpao->nome }}</td>
                        <td>
                            <span class="badge {{ $lote->status->badgeListagem() }}">{{ $lote->status->label() }}</span>
                        </td>
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
                                        <i class="ri-pencil-line"></i> Matriz
                                    </a>
                                @endcan
                                @if ($lote->status === \App\Enums\CaptacaoLoteStatus::CaptacaoEmAndamento)
                                    @can('captacao.lote.excluir')
                                        <form method="post"
                                              action="{{ route('admin.captacao.lotes.destroy', $lote) }}"
                                              class="d-inline"
                                              data-confirm="Excluir a captação de {{ $lote->data_referencia->format('d/m/Y') }} ({{ $lote->carteira?->nome ?? 'carteira' }})? Todos os pedidos e vínculos deste lote serão removidos. Esta ação não pode ser desfeita."
                                              data-confirm-title="Excluir captação"
                                              data-confirm-variant="danger"
                                              data-confirm-btn="Excluir">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="btn btn-outline-danger btn-sm"
                                                    title="Excluir captação em andamento">
                                                <i class="ri-delete-bin-line"></i> Excluir
                                            </button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">Nenhuma captação encontrada.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $lotes->links() }}
        </div>
    </div>
@endsection

@include('admin.captacao._search-select-scripts')
