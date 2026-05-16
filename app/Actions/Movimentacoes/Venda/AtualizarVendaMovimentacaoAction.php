<?php

namespace App\Actions\Movimentacoes\Venda;

use App\Http\Requests\Admin\Movimentacoes\UpdateVendaMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\VendaMovimentacaoService;

final class AtualizarVendaMovimentacaoAction
{
    public function __construct(
        private readonly VendaMovimentacaoService $venda,
    ) {}

    public function __invoke(UpdateVendaMovimentacaoRequest $request, Movimentacao $movimentacaoVenda): Movimentacao
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        return $this->venda->atualizarVenda($movimentacaoVenda, $payload, $request->user());
    }
}
