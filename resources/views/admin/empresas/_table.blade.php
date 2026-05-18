@php
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $isPaginator = $empresas instanceof LengthAwarePaginator;
    $linhas = $isPaginator ? $empresas->items() : $empresas;
@endphp

<div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-centered table-hover mb-0">
            <thead class="bg-light bg-opacity-50">
                <tr>
                    <x-admin.sortable-th label="Tipo" sort="tipo_registro" :filtros="$filtros" />
                    <x-admin.sortable-th label="# CI." sort="id_cigam" :filtros="$filtros" />
                    <x-admin.sortable-th label="Nome" sort="nome_exibicao" :filtros="$filtros" />
                    <x-admin.sortable-th label="Doc." sort="documento" :filtros="$filtros" />
                    <x-admin.sortable-th label="UN" sort="unidade_referencia" :filtros="$filtros" />
                    <x-admin.sortable-th label="Pessoa" sort="tipo_pessoa" :filtros="$filtros" />
                    <x-admin.sortable-th label="Status" sort="status" :filtros="$filtros" />
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $empresa)
                    <tr class="{{ $empresa->statusExibicao() ? '' : 'text-muted bg-light bg-opacity-25' }}">
                        <td><span class="badge bg-secondary-subtle text-secondary">{{ $empresa->rotuloTipoRegistro() }}</span></td>
                        <td><code>{{ $empresa->idCigamExibicao() }}</code></td>
                        <td><span class="fw-semibold">{{ $empresa->fantasiaExibicao() ?: $empresa->nomeExibicao() }}</span></td>
                        <td>{{ $empresa->documentoFormatado() }}</td>
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
                            <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                @php
                                    $urlEditar = $empresa->urlModuloEdicao();
                                @endphp
                                @if ($urlEditar)
                                    <a href="{{ $urlEditar }}"
                                       class="btn btn-sm btn-soft-primary"
                                       title="Editar cadastro de origem">
                                        <i class="ri-pencil-line"></i> Editar
                                    </a>
                                @endif

                                @can('empresas.historico')
                                    <a href="{{ route('admin.empresas.historico', $empresa) }}"
                                       class="btn btn-sm btn-soft-info"
                                       title="Histórico do registro corporativo">
                                        <i class="ri-history-line"></i> Histórico
                                    </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            @if (($filtros['search'] ?? '') !== '' || ($filtros['status'] ?? null) !== null || ($filtros['tipo_entidade'] ?? null) !== null)
                                Nenhum registro corresponde aos filtros aplicados.
                            @else
                                Nenhum registro no hub corporativo.
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
        Exibindo <strong>{{ $exibindo }}</strong> de <strong>{{ $total }}</strong> registro(s).
        @if (($filtros['search'] ?? '') !== '')
            · Pesquisa: <code>{{ $filtros['search'] }}</code>
        @endif
        @if (($filtros['status'] ?? null) !== null)
            · Status: <code>{{ $filtros['status'] === '1' ? 'Ativos / ativas' : 'Somente unidades inativas' }}</code>
        @endif
        @if (($filtros['tipo_entidade'] ?? null) !== null)
            · Tipo: <code>{{ $filtros['tipo_entidade'] }}</code>
        @endif
    </div>
    <x-admin.table-pagination :paginator="$empresas" />
</div>
