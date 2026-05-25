@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Cliente>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Cliente> $clientes */
    $linhas = $clientes;
@endphp

<div class="card-body">
    <table id="clientes-datatable" class="table table-sm table-striped table-hover table-centered admin-datatable-table mb-0 w-100">
            <thead>
                <tr>
                    <th># CI.</th>
                    <th>Cliente</th>
                    <th>Doc.</th>
                    <th>Praça</th>
                    <th>Grupo</th>
                    <th>Desc.</th>
                    <th>Criado</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $cliente)
                    <tr>
                        <td data-order="{{ (int) $cliente->id_cigam }}">
                            <code class="small">{{ $cliente->id_cigam }}</code>
                        </td>
                        <td data-order="{{ $cliente->fantasia ?: $cliente->razao_social }}">
                            <span class="fw-semibold">{{ $cliente->fantasia ?: $cliente->razao_social }}</span>
                        </td>
                        <td><code class="small">{{ $cliente->cnpj_cpf_formatado }}</code></td>
                        <td>{{ $cliente->praca?->nome ?? '—' }}</td>
                        <td>{{ $cliente->grupo?->nome ?? '—' }}</td>
                        <td data-order="{{ (float) $cliente->desconto_nf }}">{{ number_format((float) $cliente->desconto_nf, 2, ',', '.') }}</td>
                        <td data-order="{{ $cliente->created_at?->timestamp ?? 0 }}">{{ optional($cliente->created_at)->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1 justify-content-end flex-nowrap">
                                @can('clientes.editar')
                                    <a href="{{ route('admin.clientes.edit', $cliente) }}"
                                       class="admin-datatable-action-link text-primary"
                                       title="Editar">
                                        <i class="ri-pencil-line"></i>
                                    </a>
                                @endcan

                                @can('clientes.historico')
                                    <a href="{{ route('admin.clientes.historico', $cliente) }}"
                                       class="admin-datatable-action-link text-info"
                                       title="Histórico">
                                        <i class="ri-history-line"></i>
                                    </a>
                                @endcan

                                @canany(['captacao.cliente_fruta.vincular', 'captacao.pedido.editar', 'captacao.lote.visualizar'])
                                    <a href="{{ route('admin.captacao.frutas-por-loja.show', $cliente) }}"
                                       class="admin-datatable-action-link text-success"
                                       title="Detalhe — frutas da loja (captação)">
                                        <i class="ri-apple-line"></i>
                                    </a>
                                @endcanany
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">Nenhum cliente cadastrado.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
</div>
