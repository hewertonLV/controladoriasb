@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\GrupoContrato>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\GrupoContrato> $gruposContrato */
    $linhas = $gruposContrato;
@endphp

<div class="card-body">
    <table id="grupos-contrato-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Status</th>
                <th>Membros</th>
                <th>Último desconto</th>
                <th>Criado</th>
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
                    <td data-order="{{ $grupo->ativo ? 1 : 0 }}">
                        <span class="badge {{ $grupo->ativo ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                            {{ $grupo->ativo ? 'Ativo' : 'Inativo' }}
                        </span>
                    </td>
                    <td data-order="{{ (int) ($grupo->membros_count ?? 0) }}">{{ $grupo->membros_count ?? 0 }}</td>
                    <td>
                        @if ($ultimoDesconto)
                            <span data-order="{{ $ultimoDesconto->competencia }}">
                                {{ $ultimoDesconto->competencia }} · R$ {{ number_format((float) $ultimoDesconto->valor, 2, ',', '.') }}
                            </span>
                        @else
                            <span class="text-muted" data-order="">Sem lançamento</span>
                        @endif
                    </td>
                    <td data-order="{{ $grupo->created_at?->timestamp ?? 0 }}">{{ optional($grupo->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1 justify-content-end flex-nowrap">
                            @can('grupos-contrato.visualizar')
                                <a href="{{ route('admin.grupos-contrato.show', $grupo) }}"
                                   class="admin-datatable-action-link text-info"
                                   title="Ver">
                                    <i class="ri-eye-line"></i>
                                </a>
                            @endcan
                            @can('grupos-contrato.editar')
                                <a href="{{ route('admin.grupos-contrato.edit', $grupo) }}"
                                   class="admin-datatable-action-link text-primary"
                                   title="Editar">
                                    <i class="ri-pencil-line"></i>
                                </a>
                            @endcan
                            @can('grupos-contrato.historico')
                                <a href="{{ route('admin.grupos-contrato.historico', $grupo) }}"
                                   class="admin-datatable-action-link text-secondary"
                                   title="Histórico">
                                    <i class="ri-history-line"></i>
                                </a>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">Nenhum grupo de contrato cadastrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
