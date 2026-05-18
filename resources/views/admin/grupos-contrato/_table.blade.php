@php
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $isPaginator = $gruposContrato instanceof LengthAwarePaginator;
    $linhas = $isPaginator ? $gruposContrato->items() : $gruposContrato;
@endphp

<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-centered table-hover mb-0">
            <thead class="bg-light bg-opacity-50">
                <tr>
                    <x-admin.sortable-th label="Nome" sort="nome" :filtros="$filtros" />
                    <th>Status</th>
                    <th>Membros</th>
                    <th>Último desconto</th>
                    <x-admin.sortable-th label="Criado" sort="created_at" :filtros="$filtros" />
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $grupo)
                    @php($ultimoDesconto = $grupo->descontos->first())
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $grupo->nome }}</span>
                            @if ($grupo->descricao)
                                <div class="text-muted small">{{ $grupo->descricao }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $grupo->ativo ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                                {{ $grupo->ativo ? 'Ativo' : 'Inativo' }}
                            </span>
                        </td>
                        <td>{{ $grupo->membros_count ?? 0 }}</td>
                        <td>
                            @if ($ultimoDesconto)
                                {{ $ultimoDesconto->competencia }} · R$ {{ number_format((float) $ultimoDesconto->valor, 2, ',', '.') }}
                            @else
                                <span class="text-muted">Sem lançamento</span>
                            @endif
                        </td>
                        <td>{{ optional($grupo->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                @can('grupos-contrato.visualizar')
                                    <a href="{{ route('admin.grupos-contrato.show', $grupo) }}" class="btn btn-sm btn-soft-info">
                                        <i class="ri-eye-line"></i> Ver
                                    </a>
                                @endcan
                                @can('grupos-contrato.editar')
                                    <a href="{{ route('admin.grupos-contrato.edit', $grupo) }}" class="btn btn-sm btn-soft-primary">
                                        <i class="ri-pencil-line"></i> Editar
                                    </a>
                                @endcan
                                @can('grupos-contrato.historico')
                                    <a href="{{ route('admin.grupos-contrato.historico', $grupo) }}" class="btn btn-sm btn-soft-secondary">
                                        <i class="ri-history-line"></i> Histórico
                                    </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            Nenhum grupo de contrato cadastrado.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card-footer d-flex flex-wrap align-items-center gap-2">
    <div class="text-muted small me-auto">
        Exibindo <strong>{{ $exibindo }}</strong> de <strong>{{ $total }}</strong> grupo(s) de contrato.
    </div>
    <x-admin.table-pagination :paginator="$gruposContrato" />
</div>
