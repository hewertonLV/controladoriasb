@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Movimentacao>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Movimentacao> $movimentacoes */
    $linhas = $movimentacoes;
@endphp

<div class="card-body">
    <table id="devolucoes-movimentacao-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
        <thead>
            <tr>
                <th>Data</th>
                <th>NF dev.</th>
                <th>NF venda</th>
                <th>Tipo</th>
                <th>Fruta</th>
                <th class="text-end">Kg</th>
                <th class="text-end">Devol.</th>
                <th class="text-end">Estorno</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $m)
                <tr>
                    <td data-order="{{ $m->data_movimentacao?->timestamp ?? 0 }}">{{ $m->data_movimentacao?->format('d/m/Y H:i') }}</td>
                    <td>{{ $m->numero_nf_devolucao }}</td>
                    <td>{{ $m->vendaOrigem?->vendaNota?->numero_nf ?? '—' }}</td>
                    <td>{{ str_replace('_', ' ', $m->tipo_devolucao ?? '') }}</td>
                    <td>{{ $m->fruta?->nome ?? '—' }}</td>
                    <td class="text-end" data-order="{{ (float) $m->qtd_fruta_kg }}">{{ number_format((float) $m->qtd_fruta_kg, 2, ',', '.') }}</td>
                    <td class="text-end" data-order="{{ (float) $m->valor_devolucao_total }}">R$ {{ number_format((float) $m->valor_devolucao_total, 2, ',', '.') }}</td>
                    <td class="text-end" data-order="{{ (float) $m->resultado_devolucao }}">R$ {{ number_format((float) $m->resultado_devolucao, 2, ',', '.') }}</td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1 justify-content-end">
                            @can('movimentacoes.devolucoes.visualizar')
                                <a href="{{ route('admin.movimentacoes.devolucoes.show', $m) }}"
                                   class="admin-datatable-action-link text-info"
                                   title="Ver">
                                    <i class="ri-eye-line"></i>
                                </a>
                            @endcan
                            @can('movimentacoes.devolucoes.editar')
                                <a href="{{ route('admin.movimentacoes.devolucoes.edit', $m) }}"
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
                    <td colspan="9" class="text-center text-muted py-4">Nenhuma devolução registrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
