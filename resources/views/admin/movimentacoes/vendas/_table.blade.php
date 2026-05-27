@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Movimentacao>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Movimentacao> $movimentacoes */
    $linhas = $movimentacoes;
@endphp

<div class="card-body">
    <table id="vendas-movimentacao-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
        <thead>
            <tr>
                <th>Data</th>
                <th>NF</th>
                <th>Origem</th>
                <th>Cliente</th>
                <th>Fruta</th>
                <th class="text-end">Kg</th>
                <th class="text-end">Venda</th>
                <th class="text-end">Res.</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $m)
                <tr>
                    <td data-order="{{ $m->data_movimentacao?->timestamp ?? 0 }}">{{ $m->data_movimentacao?->format('d/m/Y H:i') }}</td>
                    <td>{{ $m->vendaNota?->numero_nf ?? '—' }}</td>
                    <td>{{ $m->empresaOrigem?->nomeExibicao() ?? '—' }}</td>
                    <td>{{ $m->empresaDestino?->nomeExibicao() ?? '—' }}</td>
                    <td>{{ $m->fruta?->nome ?? '—' }}</td>
                    <td class="text-end" data-order="{{ (float) $m->qtd_fruta_kg }}">{{ number_format((float) $m->qtd_fruta_kg, 2, ',', '.') }}</td>
                    <td class="text-end" data-order="{{ (float) $m->valor_nf_total }}">R$ {{ number_format((float) $m->valor_nf_total, 2, ',', '.') }}</td>
                    <td class="text-end" data-order="{{ (float) $m->resultado_movimentacao }}">R$ {{ number_format((float) $m->resultado_movimentacao, 2, ',', '.') }}</td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1 justify-content-end">
                            @can('movimentacoes.vendas.visualizar')
                                <a href="{{ route('admin.movimentacoes.vendas.show', $m) }}"
                                   class="admin-datatable-action-link text-info"
                                   title="Ver">
                                    <i class="ri-eye-line"></i>
                                </a>
                            @endcan
                            @can('movimentacoes.vendas.editar')
                                <a href="{{ route('admin.movimentacoes.vendas.edit', $m) }}"
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
                    <td colspan="9" class="text-center text-muted py-4">Nenhuma venda registrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
