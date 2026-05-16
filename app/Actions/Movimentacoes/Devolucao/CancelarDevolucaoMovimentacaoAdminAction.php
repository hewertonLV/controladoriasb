<?php

namespace App\Actions\Movimentacoes\Devolucao;

use App\Http\Requests\Admin\Movimentacoes\CancelarDevolucaoMovimentacaoAdminRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\CancelarDevolucaoMovimentacaoAdminService;

final class CancelarDevolucaoMovimentacaoAdminAction
{
    public function __construct(
        private readonly CancelarDevolucaoMovimentacaoAdminService $service,
    ) {}

    public function __invoke(CancelarDevolucaoMovimentacaoAdminRequest $request, Movimentacao $movimentacaoDevolucao): void
    {
        $this->service->executar(
            $movimentacaoDevolucao,
            $request->user(),
            trim((string) $request->validated('motivo')),
        );
    }
}
