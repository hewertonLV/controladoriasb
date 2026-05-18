@php
    use Illuminate\Contracts\Pagination\LengthAwarePaginator;
    $isPaginator = $estoques instanceof LengthAwarePaginator;
    $linhas = $isPaginator ? $estoques->items() : $estoques;
@endphp

<div class="card-body">
    <div class="estoques-table-container">
        <table id="estoques-datatable" class="table table-sm table-striped table-hover table-centered fornecedores-table mb-0 w-100">
            <thead>
                <tr>
                    <th># CI.</th>
                    <th>Fruta</th>
                    <th>Qtd.</th>
                    <th>Custo</th>
                    <th>Total</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($linhas as $estoque)
                    <tr>
                        <td><code class="small">{{ $estoque->fruta->id_cigam ?? '' }}</code></td>
                        <td>
                            <span class="fw-semibold">{{ $estoque->fruta->nome ?? '—' }}</span>
                        </td>
                        <td>
                            {{ number_format((float) $estoque->qtd_fruta_kg, 2, ',', '.') }} kg<br>
                            <span class="text-muted">{{ number_format((float) $estoque->qtd_fruta_um, 2, ',', '.') }} {{ $estoque->fruta->unidade_medicao ?? '' }}</span>
                        </td>
                        <td>
                            R$ {{ number_format((float) $estoque->preco_medio_kg, 2, ',', '.') }}/kg<br>
                            <span class="text-muted">R$ {{ number_format((float) $estoque->preco_medio_um, 2, ',', '.') }}/UM</span>
                        </td>
                        <td>R$ {{ number_format((float) $estoque->valor_total_acumulado, 2, ',', '.') }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 justify-content-end">
                                @can('estoques.visualizar')
                                    <a href="{{ route('admin.estoques.show', $estoque) }}"
                                       class="fornecedor-action-link text-secondary"
                                       title="Detalhes">
                                        <i class="ri-eye-line"></i>
                                    </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
