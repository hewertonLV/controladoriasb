@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Empresa>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Empresa> $empresas */
    $linhas = $empresas;
@endphp

<div class="card-body">
    <table id="empresas-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th># CI.</th>
                    <th>Nome</th>
                    <th>Doc.</th>
                    <th>UN</th>
                    <th>Pessoa</th>
                    <th>Status</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $empresa)
                    <tr class="{{ $empresa->statusExibicao() ? '' : 'text-muted bg-light bg-opacity-25' }}">
                        <td><span class="badge bg-secondary-subtle text-secondary">{{ $empresa->rotuloTipoRegistro() }}</span></td>
                        <td data-order="{{ (int) ltrim((string) $empresa->idCigamExibicao(), '0') ?: 0 }}">
                            <code class="small">{{ $empresa->idCigamExibicao() }}</code>
                        </td>
                        <td><span class="fw-semibold">{{ $empresa->fantasiaExibicao() ?: $empresa->nomeExibicao() }}</span></td>
                        <td><code class="small">{{ $empresa->documentoFormatado() }}</code></td>
                        <td>{{ $empresa->unidadeNegocioExibicao() }}</td>
                        <td>
                            @if ($empresa->tipoPessoaExibicao() === 'FISICA')
                                <span class="badge bg-info-subtle text-info">Física</span>
                            @elseif ($empresa->tipoPessoaExibicao() === 'JURIDICA')
                                <span class="badge bg-primary-subtle text-primary">Jurídica</span>
                            @else
                                <span class="badge bg-light text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if ($empresa->statusExibicao())
                                <span class="badge bg-success-subtle text-success">
                                    <i class="ri-checkbox-circle-line me-1"></i>Ativa
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary">
                                    <i class="ri-close-circle-line me-1"></i>Inativa
                                </span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 justify-content-end">
                                @php
                                    $urlEditar = $empresa->urlModuloEdicao();
                                @endphp
                                @if ($urlEditar)
                                    <a href="{{ $urlEditar }}"
                                       class="admin-datatable-action-link text-primary"
                                       title="Editar cadastro de origem">
                                        <i class="ri-pencil-line"></i>
                                    </a>
                                @endif

                                @can('empresas.historico')
                                    <a href="{{ route('admin.empresas.historico', $empresa) }}"
                                       class="admin-datatable-action-link text-info"
                                       title="Histórico do registro corporativo">
                                        <i class="ri-history-line"></i>
                                    </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Nenhum registro no hub corporativo.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
</div>
