@php
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $isPaginator = $fretes instanceof LengthAwarePaginator;
    $linhas = $isPaginator ? $fretes->items() : $fretes;

    $fmtMoeda = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
@endphp

<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-centered table-hover mb-0">
            <thead class="bg-light bg-opacity-50">
                <tr>
                    <x-admin.sortable-th label="Nome" sort="nome" :filtros="$filtros" />
                    <x-admin.sortable-th label="Valor" sort="valor" :filtros="$filtros" />
                    <th>Veículo (ID SBS)</th>
                    <x-admin.sortable-th label="Situação" sort="status_situacao" :filtros="$filtros" />
                    <x-admin.sortable-th label="Valor fruta/kg" sort="valor_fruta_kg" :filtros="$filtros" />
                    <x-admin.sortable-th label="Criado em" sort="created_at" :filtros="$filtros" />
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $frete)
                    <tr>
                        <td><span class="fw-semibold">{{ $frete->nome }}</span></td>
                        <td>{{ $fmtMoeda($frete->valor) }}</td>
                        <td>
                            @if ($frete->veiculo)
                                <code>{{ $frete->veiculo->id_sbs }}</code>
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
                        <td>{{ $fmtMoeda($frete->valor_fruta_kg) }}</td>
                        <td>{{ optional($frete->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                @can('fretes.editar')
                                    <a href="{{ route('admin.fretes.edit', $frete) }}"
                                       class="btn btn-sm btn-soft-primary"
                                       title="Editar">
                                        <i class="ri-pencil-line"></i> Editar
                                    </a>
                                @endcan

                                @can('fretes.historico')
                                    <a href="{{ route('admin.fretes.historico', $frete) }}"
                                       class="btn btn-sm btn-soft-info"
                                       title="Histórico">
                                        <i class="ri-history-line"></i> Histórico
                                    </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">
                            @if (($filtros['search'] ?? '') !== '' || ($filtros['status_situacao'] ?? null))
                                Nenhum frete corresponde aos filtros aplicados.
                            @else
                                Nenhum frete cadastrado.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card-footer d-flex flex-wrap align-items-center gap-2">
    <div class="text-muted small me-auto">
        Exibindo <strong>{{ $exibindo }}</strong> de <strong>{{ $total }}</strong> frete(s).
        @if (($filtros['search'] ?? '') !== '')
            · Pesquisa: <code>{{ $filtros['search'] }}</code>
        @endif
        @if (($filtros['status_situacao'] ?? null))
            · Situação: <code>{{ $filtros['status_situacao'] }}</code>
        @endif
    </div>
    <x-admin.table-pagination :paginator="$fretes" />
</div>
