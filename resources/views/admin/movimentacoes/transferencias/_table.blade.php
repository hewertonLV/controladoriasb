@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Movimentacao>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Movimentacao> $movimentacoes */
    $linhas = $movimentacoes;
@endphp

<div class="card-body">
    <table id="transferencias-movimentacao-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
        <thead>
            <tr>
                <th>Data</th>
                <th>Origem</th>
                <th>Destino</th>
                <th>Fruta</th>
                <th>Status</th>
                <th class="text-end">UM</th>
                <th class="text-end">Kg</th>
                <th>Frete</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $m)
                <tr>
                    <td data-order="{{ $m->data_movimentacao?->timestamp ?? 0 }}">{{ $m->data_movimentacao?->format('d/m/Y H:i') }}</td>
                    <td>{{ $m->empresaOrigem?->nomeExibicao() ?? '—' }}</td>
                    <td>{{ $m->empresaDestino?->nomeExibicao() ?? '—' }}</td>
                    <td>{{ $m->fruta?->nome ?? '—' }}</td>
                    <td><span class="badge bg-secondary">{{ $m->status_transferencia ?? '—' }}</span></td>
                    <td class="text-end" data-order="{{ (float) $m->qtd_fruta_um }}">{{ number_format((float) $m->qtd_fruta_um, 2, ',', '.') }}</td>
                    <td class="text-end" data-order="{{ (float) $m->qtd_fruta_kg }}">{{ number_format((float) $m->qtd_fruta_kg, 2, ',', '.') }}</td>
                    <td>{{ $m->frete?->nome ?? '—' }}</td>
                    <td class="text-end">
                        @can('movimentacoes.transferencias.visualizar')
                            <a href="{{ route('admin.movimentacoes.transferencias.show', ['transferenciaOrigem' => $m->transferencia_origem_id]) }}"
                               class="admin-datatable-action-link text-info"
                               title="Ver">
                                <i class="ri-eye-line"></i>
                            </a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">Nenhuma transferência registrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
