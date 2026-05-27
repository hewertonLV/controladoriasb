@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Movimentacao>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Movimentacao> $movimentacoes */
    $linhas = $movimentacoes;
@endphp

<div class="card-body">
    <table id="entradas-estoque-movimentacao-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
        <thead>
            <tr>
                <th>Data</th>
                <th>Unidade</th>
                <th>Fruta</th>
                <th class="text-end">Qtd UM</th>
                <th class="text-end">Preço / UM</th>
                <th class="text-end">Valor total</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $m)
                <tr>
                    <td data-order="{{ $m->data_movimentacao?->timestamp ?? 0 }}">{{ $m->data_movimentacao?->format('d/m/Y H:i') }}</td>
                    <td>{{ $m->empresaOrigem?->nomeExibicao() ?? '—' }}</td>
                    <td>{{ $m->fruta?->nome ?? '—' }}</td>
                    <td class="text-end" data-order="{{ (float) $m->qtd_fruta_um }}">{{ number_format((float) $m->qtd_fruta_um, 2, ',', '.') }}</td>
                    <td class="text-end" data-order="{{ (float) $m->valor_nf_um }}">R$ {{ number_format((float) $m->valor_nf_um, 2, ',', '.') }}</td>
                    <td class="text-end" data-order="{{ (float) $m->valor_nf_total }}">R$ {{ number_format((float) $m->valor_nf_total, 2, ',', '.') }}</td>
                    <td class="text-end">
                        @can('movimentacoes.entradas-estoque.visualizar')
                            <a href="{{ route('admin.movimentacoes.entradas-estoque.show', $m) }}"
                               class="admin-datatable-action-link text-info"
                               title="Ver">
                                <i class="ri-eye-line"></i>
                            </a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">Nenhuma entrada de estoque registrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
