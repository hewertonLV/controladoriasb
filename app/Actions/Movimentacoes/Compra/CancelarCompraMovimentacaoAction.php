<?php

namespace App\Actions\Movimentacoes\Compra;

use App\Http\Requests\Admin\Movimentacoes\CancelarCompraMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\CancelarCompraMovimentacaoService;

final class CancelarCompraMovimentacaoAction
{
    public function __construct(
        private readonly CancelarCompraMovimentacaoService $service,
    ) {}

    public function __invoke(CancelarCompraMovimentacaoRequest $request, Movimentacao $movimentacao): void
    {
        $this->service->executar(
            $movimentacao,
            $request->user(),
            trim((string) $request->validated('motivo')),
        );
    }
}
