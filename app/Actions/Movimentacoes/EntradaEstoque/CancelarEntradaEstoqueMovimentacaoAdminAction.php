<?php

namespace App\Actions\Movimentacoes\EntradaEstoque;

use App\Http\Requests\Admin\Movimentacoes\CancelarEntradaEstoqueMovimentacaoAdminRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\CancelarEntradaEstoqueMovimentacaoAdminService;

final class CancelarEntradaEstoqueMovimentacaoAdminAction
{
    public function __construct(
        private readonly CancelarEntradaEstoqueMovimentacaoAdminService $service,
    ) {}

    public function __invoke(CancelarEntradaEstoqueMovimentacaoAdminRequest $request, Movimentacao $movimentacao): void
    {
        $this->service->executar(
            $movimentacao,
            $request->user(),
            (string) $request->validated('motivo'),
        );
    }
}
