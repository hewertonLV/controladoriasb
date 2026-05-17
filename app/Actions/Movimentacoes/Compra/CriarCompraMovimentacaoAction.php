<?php

namespace App\Actions\Movimentacoes\Compra;

use App\Http\Requests\Admin\Movimentacoes\StoreCompraMovimentacaoRequest;
use App\Services\Movimentacoes\CompraMovimentacaoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CriarCompraMovimentacaoAction
{
    public function __construct(
        private readonly CompraMovimentacaoService $compra,
    ) {}

    /**
     * @return Collection<int, \App\Models\Movimentacao>
     */
    public function __invoke(StoreCompraMovimentacaoRequest $request): Collection
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->validated();
        $itens = $payload['itens'] ?? [[
            'id_fruta' => $payload['id_fruta'],
            'qtd_fruta_um' => $payload['qtd_fruta_um'],
            'valor_nf_total' => $payload['valor_nf_total'],
        ]];

        unset($payload['itens'], $payload['id_fruta'], $payload['qtd_fruta_um'], $payload['valor_nf_total']);

        return DB::transaction(function () use ($payload, $itens): Collection {
            return collect($itens)
                ->map(fn (array $item) => $this->compra->registrarCompra(array_merge($payload, $item)))
                ->values();
        });
    }
}
