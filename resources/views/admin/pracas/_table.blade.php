@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Praca>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Praca> $pracas */
    $linhas = $pracas;
@endphp

<div class="card-body">
    <table id="pracas-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>UN</th>
                    <th>Criado</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $praca)
                    <tr>
                        <td><span class="fw-semibold">{{ $praca->nome }}</span></td>
                        <td data-order="{{ $praca->id_unidade_negocio }}">
                            @if ($praca->unidadeNegocio)
                                <span title="ID {{ $praca->id_unidade_negocio }}">
                                    {{ $praca->unidadeNegocio->nome }}
                                    <small class="text-muted">({{ $praca->unidadeNegocio->id_cigam }})</small>
                                </span>
                            @else
                                <code class="small">{{ $praca->id_unidade_negocio }}</code>
                            @endif
                        </td>
                        <td data-order="{{ $praca->created_at?->timestamp ?? 0 }}">{{ optional($praca->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 justify-content-end">
                                @can('pracas.editar')
                                    <a href="{{ route('admin.pracas.edit', $praca) }}"
                                       class="admin-datatable-action-link text-primary"
                                       title="Editar">
                                        <i class="ri-pencil-line"></i>
                                    </a>
                                @endcan

                                @can('pracas.historico')
                                    <a href="{{ route('admin.pracas.historico', $praca) }}"
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
                        <td colspan="4" class="text-center text-muted py-4">Nenhuma praça cadastrada.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
</div>
