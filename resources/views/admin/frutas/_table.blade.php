@php
    use App\Enums\FrutaUnidadeMedicao;
    /** @var \Illuminate\Support\Collection<int, \App\Models\Fruta>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Fruta> $frutas */
    $linhas = $frutas;
@endphp

<div class="card-body">
    <table id="frutas-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
        <thead>
            <tr>
                <th># CI.</th>
                <th>Nome</th>
                <th>UM</th>
                <th>Kg/UM</th>
                <th>ICMS</th>
                <th>Criado</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $fruta)
                <tr>
                    <td data-order="{{ (int) $fruta->id_cigam }}">
                        <code class="small">{{ $fruta->id_cigam }}</code>
                    </td>
                    <td><span class="fw-semibold">{{ $fruta->nome }}</span></td>
                    <td>{{ $fruta->unidade_medicao }}</td>
                    @php
                        $casasKg = FrutaUnidadeMedicao::tryFrom((string) $fruta->unidade_medicao)?->casasDecimaisKg() ?? 2;
                    @endphp
                    <td data-order="{{ (float) $fruta->kg_por_unidade_medicao }}">{{ number_format((float) $fruta->kg_por_unidade_medicao, $casasKg, ',', '.') }}</td>
                    <td>
                        <span class="badge bg-light text-muted">{{ (int) ($fruta->icms_count ?? 0) }} registro(s)</span>
                        <span class="text-muted small d-block">Por estado</span>
                    </td>
                    <td data-order="{{ $fruta->created_at?->timestamp ?? 0 }}">{{ optional($fruta->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1 justify-content-end flex-nowrap">
                            @can('frutas.editar')
                                <a href="{{ route('admin.frutas.edit', $fruta) }}"
                                   class="admin-datatable-action-link text-primary"
                                   title="Editar">
                                    <i class="ri-pencil-line"></i>
                                </a>
                            @endcan

                            @can('frutas.historico')
                                <a href="{{ route('admin.frutas.historico', $fruta) }}"
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
                    <td colspan="7" class="text-center text-muted py-4">Nenhuma fruta cadastrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
