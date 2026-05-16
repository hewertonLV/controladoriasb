<?php

namespace App\Actions\Movimentacoes\Descarte;

use App\Http\Requests\Admin\Movimentacoes\CancelarDescarteMovimentacaoAdminRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\CancelarDescarteMovimentacaoAdminService;

final class CancelarDescarteMovimentacaoAdminAction
{
    public function __construct(
        private readonly CancelarDescarteMovimentacaoAdminService $service,
    ) {}

    public function __invoke(CancelarDescarteMovimentacaoAdminRequest $request, Movimentacao $movimentacaoDescarte): void
    {
        $this->service->executar(
            $movimentacaoDescarte,
            $request->user(),
            trim((string) $request->validated('motivo')),
        );
    }
}
