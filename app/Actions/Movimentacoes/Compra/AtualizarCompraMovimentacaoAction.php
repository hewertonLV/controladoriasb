<?php

namespace App\Actions\Movimentacoes\Compra;

use App\Http\Requests\Admin\Movimentacoes\UpdateCompraMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\CompraMovimentacaoService;

final class AtualizarCompraMovimentacaoAction
{
    public function __construct(
        private readonly CompraMovimentacaoService $compra,
    ) {}

    public function __invoke(Movimentacao $movimentacao, UpdateCompraMovimentacaoRequest $request): Movimentacao
    {
        /** @var array{valor_nf_total: numeric-string|float|int|string, motivo_substituicao?: string|null} $payload */
        $payload = $request->validated();

        return $this->compra->atualizarCompra($movimentacao, $payload, $request->user());
    }
}
