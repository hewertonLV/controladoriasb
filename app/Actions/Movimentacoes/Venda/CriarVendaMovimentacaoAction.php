<?php

namespace App\Actions\Movimentacoes\Venda;

use App\Http\Requests\Admin\Movimentacoes\StoreVendaMovimentacaoRequest;
use App\Services\Movimentacoes\VendaMovimentacaoService;

final class CriarVendaMovimentacaoAction
{
    public function __construct(
        private readonly VendaMovimentacaoService $venda,
    ) {}

    public function __invoke(StoreVendaMovimentacaoRequest $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        return $this->venda->registrarVenda($payload, $request->user());
    }
}
