@extends('layouts.app')

@section('title', 'Histórico — ' . $grupoContrato->nome)
@section('page-title', 'Histórico do Grupo de Contrato')

@section('content')
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <div class="me-auto">
                <h4 class="header-title mb-1">{{ $grupoContrato->nome }}</h4>
                <p class="text-muted mb-0">Eventos do cadastro do grupo de contrato.</p>
            </div>
            <a href="{{ route('admin.grupos-contrato.show', $grupoContrato) }}" class="btn btn-light">
                <i class="ri-arrow-left-line me-1"></i> Voltar
            </a>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-centered mb-0">
                    <thead>
                        <tr>
                            <th>Quando</th>
                            <th>Ação</th>
                            <th>Origem</th>
                            <th>Usuário</th>
                            <th>Alterações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($historicos as $historico)
                            <tr>
                                <td>{{ optional($historico->created_at)->format('d/m/Y H:i:s') ?? '—' }}</td>
                                <td>{{ $historico->acao }}</td>
                                <td>{{ $historico->origem }}</td>
                                <td>{{ $historico->user?->name ?? '—' }}</td>
                                <td>
                                    @if (! empty($historico->alteracoes))
                                        <ul class="mb-0 ps-3">
                                            @foreach ($historico->alteracoes as $alteracao)
                                                <li>{{ $alteracao['campo'] ?? 'campo' }}</li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Nenhum histórico registrado.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($historicos->hasPages())
            <div class="card-footer">{{ $historicos->links() }}</div>
        @endif
    </div>
@endsection
