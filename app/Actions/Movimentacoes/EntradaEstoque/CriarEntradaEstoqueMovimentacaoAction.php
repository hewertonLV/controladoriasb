<?php

namespace App\Actions\Movimentacoes\EntradaEstoque;

use App\Http\Requests\Admin\Movimentacoes\StoreEntradaEstoqueMovimentacaoRequest;
use App\Services\Movimentacoes\EntradaEstoqueMovimentacaoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CriarEntradaEstoqueMovimentacaoAction
{
    public function __construct(
        private readonly EntradaEstoqueMovimentacaoService $entradaEstoque,
    ) {}

    /**
     * @return Collection<int, \App\Models\Movimentacao>
     */
    public function __invoke(StoreEntradaEstoqueMovimentacaoRequest $request): Collection
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->validated();
        $itens = $payload['itens'] ?? [];
        unset($payload['itens']);

        return DB::transaction(function () use ($payload, $itens, $request): Collection {
            return collect($itens)
                ->map(fn (array $item) => $this->entradaEstoque->registrarEntrada(array_merge($payload, $item), $request->user()))
                ->values();
        });
    }
}
