<?php

namespace App\Actions\Movimentacoes\Venda;

use App\Http\Requests\Admin\Movimentacoes\CancelarVendaMovimentacaoAdminRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\CancelarVendaMovimentacaoAdminService;

final class CancelarVendaMovimentacaoAdminAction
{
    public function __construct(
        private readonly CancelarVendaMovimentacaoAdminService $service,
    ) {}

    public function __invoke(CancelarVendaMovimentacaoAdminRequest $request, Movimentacao $movimentacaoVenda): void
    {
        $this->service->executar(
            $movimentacaoVenda,
            $request->user(),
            trim((string) $request->validated('motivo')),
        );
    }
}
