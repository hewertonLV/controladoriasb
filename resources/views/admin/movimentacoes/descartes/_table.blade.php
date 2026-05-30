@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Movimentacao>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Movimentacao> $movimentacoes */
    $linhas = $movimentacoes;
@endphp

<div class="card-body">
    <table id="descartes-movimentacao-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
        <thead>
            <tr>
                <th>Data</th>
                <th>Origem</th>
                <th>Fruta</th>
                <th>Categoria</th>
                <th class="text-end">UM</th>
                <th class="text-end">Kg</th>
                <th class="text-end">Valor</th>
                <th>Status</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $m)
                <tr>
                    <td data-order="{{ $m->data_movimentacao?->timestamp ?? 0 }}">{{ $m->data_movimentacao?->format('d/m/Y H:i') }}</td>
                    <td>{{ $m->empresaOrigem?->nomeExibicao() ?? '—' }}</td>
                    <td>{{ $m->fruta?->nome ?? '—' }}</td>
                    <td>{{ $m->categoriaDescarte?->nome ?? '—' }}</td>
                    <td class="text-end" data-order="{{ (float) $m->qtd_fruta_um }}">{{ number_format((float) $m->qtd_fruta_um, 2, ',', '.') }}</td>
                    <td class="text-end" data-order="{{ (float) $m->qtd_fruta_kg }}">{{ number_format((float) $m->qtd_fruta_kg, 2, ',', '.') }}</td>
                    <td class="text-end" data-order="{{ $m->valorEconomicoParaRelatorio() }}">R$ {{ number_format($m->valorEconomicoParaRelatorio(), 2, ',', '.') }}</td>
                    <td>{{ $m->status_registro }}</td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1 justify-content-end">
                            @can('movimentacoes.descartes.visualizar')
                                <a href="{{ route('admin.movimentacoes.descartes.show', $m) }}"
                                   class="admin-datatable-action-link text-info"
                                   title="Ver">
                                    <i class="ri-eye-line"></i>
                                </a>
                            @endcan
                            @can('movimentacoes.descartes.editar')
                                <a href="{{ route('admin.movimentacoes.descartes.edit', $m) }}"
                                   class="admin-datatable-action-link text-primary"
                                   title="Editar">
                                    <i class="ri-pencil-line"></i>
                                </a>
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">Nenhum descarte registrado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
