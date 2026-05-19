@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Frete>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Frete> $fretes */
    $linhas = $fretes;

    $fmtMoeda = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
@endphp

<div class="card-body">
    <table id="fretes-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Valor</th>
                    <th>Veículo</th>
                    <th>Sit.</th>
                    <th>Fruta/kg</th>
                    <th>Criado</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $frete)
                    <tr>
                        <td><span class="fw-semibold">{{ $frete->nome }}</span></td>
                        <td data-order="{{ (float) $frete->valor }}">{{ $fmtMoeda($frete->valor) }}</td>
                        <td>
                            @if ($frete->veiculo)
                                <code class="small">{{ $frete->veiculo->id_sbs }}</code>
                                <span class="text-muted small d-block">{{ $frete->veiculo->nome }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($frete->status_situacao === 'ABERTA')
                                <span class="badge bg-success-subtle text-success">Aberta</span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">Encerrada</span>
                            @endif
                        </td>
                        <td data-order="{{ (float) $frete->valor_fruta_kg }}">{{ $fmtMoeda($frete->valor_fruta_kg) }}</td>
                        <td data-order="{{ $frete->created_at?->timestamp ?? 0 }}">{{ optional($frete->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 justify-content-end flex-nowrap">
                                @can('fretes.editar')
                                    <a href="{{ route('admin.fretes.edit', $frete) }}"
                                       class="admin-datatable-action-link text-primary"
                                       title="Editar">
                                        <i class="ri-pencil-line"></i>
                                    </a>
                                @endcan

                                @can('fretes.historico')
                                    <a href="{{ route('admin.fretes.historico', $frete) }}"
                                       class="admin-datatable-action-link text-info"
                                       title="Histórico">
                                        <i class="ri-history-line"></i>
                                    </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">Nenhum frete cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
</div>
