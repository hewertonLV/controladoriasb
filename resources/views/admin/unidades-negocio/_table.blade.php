@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\UnidadeNegocio>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\UnidadeNegocio> $unidadesNegocio */
    $linhas = $unidadesNegocio;
@endphp

<div class="card-body">
    <table id="unidades-negocio-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
            <thead>
                <tr>
                    <th># CI.</th>
                    <th>UF</th>
                    <th>Unidade</th>
                    <th>Doc.</th>
                    <th>C. op.</th>
                    <th>Est.</th>
                    <th>Status</th>
                    <th>Criado</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $unidade)
                    <tr class="{{ $unidade->status ? '' : 'text-muted bg-light bg-opacity-25' }}"
                        data-id-estado="{{ $unidade->id_estado }}"
                        data-status="{{ $unidade->status ? '1' : '0' }}"
                        data-possui-estoque="{{ $unidade->possui_estoque ? '1' : '0' }}">
                        <td data-order="{{ (int) ltrim((string) $unidade->id_cigam, '0') ?: 0 }}">
                            <code class="small">{{ $unidade->id_cigam }}</code>
                        </td>
                        <td>{{ $unidade->estado?->abreviacao ?? '—' }}</td>
                        <td><span class="fw-semibold">{{ $unidade->nome ?: $unidade->razao_social }}</span></td>
                        <td><code class="small">{{ $unidade->cpf_cnpj_formatado }}</code></td>
                        <td data-order="{{ (float) $unidade->custo_operacional }}">{{ number_format((float) $unidade->custo_operacional, 2, ',', '.') }}</td>
                        <td>
                            @if ($unidade->possui_estoque)
                                <span class="badge bg-info-subtle text-info">Sim</span>
                            @else
                                <span class="badge bg-light text-muted border">Não</span>
                            @endif
                        </td>
                        <td>
                            @if ($unidade->status)
                                <span class="badge bg-success-subtle text-success">
                                    <i class="ri-checkbox-circle-line me-1"></i>Ativa
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">
                                    <i class="ri-close-circle-line me-1"></i>Inativa
                                </span>
                            @endif
                        </td>
                        <td data-order="{{ $unidade->created_at?->timestamp ?? 0 }}">{{ optional($unidade->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 justify-content-end flex-nowrap">
                                @can('unidades-negocio.editar')
                                    <a href="{{ route('admin.unidades-negocio.edit', $unidade) }}"
                                       class="admin-datatable-action-link text-primary"
                                       title="Editar">
                                        <i class="ri-pencil-line"></i>
                                    </a>
                                @endcan

                                @can('unidades-negocio.historico')
                                    <a href="{{ route('admin.unidades-negocio.historico', $unidade) }}"
                                       class="admin-datatable-action-link text-info"
                                       title="Histórico">
                                        <i class="ri-history-line"></i>
                                    </a>
                                @endcan

                                @if ($unidade->status)
                                    @can('unidades-negocio.inativar')
                                        <form method="POST"
                                              action="{{ route('admin.unidades-negocio.inativar', $unidade) }}"
                                              class="d-inline"
                                              data-confirm="{{ 'Inativar a Unidade de Negócio '.$unidade->nome.'?' }}"
                                              data-confirm-title="Inativar unidade"
                                              data-confirm-variant="danger"
                                              data-confirm-btn="Inativar">
                                            @csrf
                                            <button type="submit"
                                                    class="admin-datatable-action-link text-secondary border-0 bg-transparent p-0"
                                                    title="Inativar">
                                                <i class="ri-close-circle-line"></i>
                                            </button>
                                        </form>
                                    @endcan
                                @else
                                    @can('unidades-negocio.ativar')
                                        <form method="POST"
                                              action="{{ route('admin.unidades-negocio.ativar', $unidade) }}"
                                              class="d-inline"
                                              data-confirm="{{ 'Ativar a Unidade de Negócio '.$unidade->nome.'?' }}"
                                              data-confirm-title="Ativar unidade"
                                              data-confirm-variant="success"
                                              data-confirm-btn="Ativar">
                                            @csrf
                                            <button type="submit"
                                                    class="admin-datatable-action-link text-success border-0 bg-transparent p-0"
                                                    title="Ativar">
                                                <i class="ri-checkbox-circle-line"></i>
                                            </button>
                                        </form>
                                    @endcan
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center text-muted py-4">Nenhuma unidade de negócio cadastrada.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
</div>
