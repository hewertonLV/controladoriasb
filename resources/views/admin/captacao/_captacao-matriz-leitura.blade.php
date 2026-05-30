@php
    /** @var \App\Models\Captacao\CaptacaoLote $lote */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Cliente> $clientes */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Fruta> $frutas */
    /** @var array<int, list<int>> $frutasPorCliente */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Captacao\Pedido>|\Illuminate\Database\Eloquent\Collection<int, \App\Models\Captacao\Pedido> $pedidosPorCliente */
@endphp

<div class="table-responsive captacao-matriz-leitura-wrap">
    <table class="table table-bordered table-sm mb-0">
        <thead>
        <tr>
            <th class="text-nowrap">Loja</th>
            @foreach ($frutas as $fruta)
                <th class="text-center text-nowrap" style="min-width: 6.5rem">
                    @include('admin.captacao.matriz._legenda-fruta-vertical', ['nome' => $fruta->nome])
                </th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @forelse ($clientes as $cliente)
            @php
                $pedido = $pedidosPorCliente->get($cliente->id);
                $frutasCliente = $frutasPorCliente[$cliente->id] ?? [];
            @endphp
            <tr>
                <td class="text-nowrap">
                    <span class="fw-semibold">{{ $cliente->fantasia ?: $cliente->razao_social }}</span>
                    @if ($pedido?->numero_pedido)
                        <span class="d-block text-muted small">Pedido {{ $pedido->numero_pedido }}</span>
                    @endif
                </td>
                @foreach ($frutas as $fruta)
                    @php
                        $temVinculo = in_array($fruta->id, $frutasCliente, true);
                        $item = $pedido?->itens->firstWhere('id_fruta', $fruta->id);
                        $qtd = $item !== null ? (float) $item->quantidade : 0;
                        $preco = $item?->preco_venda !== null ? (float) $item->preco_venda : null;
                    @endphp
                    <td @class(['text-center', 'bg-light-subtle' => ! $temVinculo])>
                        @if ($temVinculo && $qtd > 0)
                            <div class="small fw-semibold">{{ (int) $qtd }}</div>
                            @if ($preco !== null && $preco > 0)
                                <div class="small text-muted">R$ {{ number_format($preco, 2, ',', '.') }}</div>
                            @else
                                <div class="small text-muted">—</div>
                            @endif
                        @elseif ($temVinculo)
                            <span class="text-muted">—</span>
                        @else
                            <span class="text-muted" title="Sem vínculo">×</span>
                        @endif
                    </td>
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ $frutas->count() + 1 }}" class="text-muted text-center">Nenhuma loja na captação.</td>
            </tr>
        @endforelse
        </tbody>
    </table>
</div>
