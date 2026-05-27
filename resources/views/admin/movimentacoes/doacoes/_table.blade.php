@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Movimentacao>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Movimentacao> $movimentacoes */
    $linhas = $movimentacoes;
@endphp

<div class="card-body">
    <table id="doacoes-movimentacao-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
        <thead>
            <tr>
                <th>Data</th>
                <th>Origem</th>
                <th>Destino</th>
                <th>Fruta</th>
                <th class="text-end">UM</th>
                <th class="text-end">Kg</th>
                <th class="text-end">Baixa</th>
                <th>Motivo</th>
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
                    <td class="text-end" data-order="{{ (float) $m->qtd_fruta_um }}">{{ number_format((float) $m->qtd_fruta_um, 2, ',', '.') }}</td>
                    <td class="text-end" data-order="{{ (float) $m->qtd_fruta_kg }}">{{ number_format((float) $m->qtd_fruta_kg, 2, ',', '.') }}</td>
                    <td class="text-end" data-order="{{ $m->valorEconomicoParaRelatorio() }}">R$ {{ number_format($m->valorEconomicoParaRelatorio(), 2, ',', '.') }}</td>
                    <td class="text-truncate" style="max-width: 12rem">{{ $m->motivo_doacao ?? '—' }}</td>
                    <td class="text-end">
                        <div class="d-inline-flex gap-1 justify-content-end">
                            @can('movimentacoes.doacoes.visualizar')
                                <a href="{{ route('admin.movimentacoes.doacoes.show', $m) }}"
                                   class="admin-datatable-action-link text-info"
                                   title="Ver">
                                    <i class="ri-eye-line"></i>
                                </a>
                            @endcan
                            @can('movimentacoes.doacoes.editar')
                                <a href="{{ route('admin.movimentacoes.doacoes.edit', $m) }}"
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
                    <td colspan="9" class="text-center text-muted py-4">Nenhuma doação registrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
