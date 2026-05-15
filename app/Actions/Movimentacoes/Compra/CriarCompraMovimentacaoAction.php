<?php

namespace App\Actions\Movimentacoes\Compra;

use App\Http\Requests\Admin\Movimentacoes\StoreCompraMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\CompraMovimentacaoService;

final class CriarCompraMovimentacaoAction
{
    public function __construct(
        private readonly CompraMovimentacaoService $compra,
    ) {}

    public function __invoke(StoreCompraMovimentacaoRequest $request): Movimentacao
    {
        /** @var array{
         *     id_empresa_origem:int,
         *     id_empresa_destino:int,
         *     id_fruta:int,
         *     qtd_fruta_um:numeric-string|float|int|string,
         *     valor_nf_total:numeric-string|float|int|string,
         *     id_frete:int,
         * } $payload */
        $payload = $request->validated();

        return $this->compra->registrarCompra($payload);
    }
}
