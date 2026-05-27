@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Movimentacao>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Movimentacao> $movimentacoes */
    $linhas = $movimentacoes;
@endphp

<div class="card-body">
    <table id="conversoes-embalagem-movimentacao-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
        <thead>
            <tr>
                <th>ID</th>
                <th>Unidade</th>
                <th>Origem</th>
                <th>Destino</th>
                <th>Perda</th>
                <th>Data</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $m)
                <tr>
                    <td data-order="{{ $m->id }}">{{ $m->id }}</td>
                    <td>{{ $m->empresaOrigem?->nomeExibicao() }}</td>
                    <td>{{ $m->fruta?->nome }} — {{ number_format((float) $m->qtd_fruta_um, 2, ',', '.') }} UM</td>
                    <td>{{ $m->frutaDestinoConversao?->nome }} — {{ number_format((float) $m->qtd_resultante_um, 2, ',', '.') }} UM</td>
                    <td data-order="{{ (float) $m->qtd_perda_conversao_kg }}">{{ number_format((float) $m->qtd_perda_conversao_um, 2, ',', '.') }} UM / {{ number_format((float) $m->qtd_perda_conversao_kg, 2, ',', '.') }} kg</td>
                    <td data-order="{{ $m->data_movimentacao?->timestamp ?? 0 }}">{{ $m->data_movimentacao?->format('d/m/Y H:i') }}</td>
                    <td class="text-end">
                        <a href="{{ route('admin.movimentacoes.conversoes-embalagem.show', $m) }}"
                           class="admin-datatable-action-link text-info"
                           title="Ver">
                            <i class="ri-eye-line"></i>
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">Nenhuma conversão registrada.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
