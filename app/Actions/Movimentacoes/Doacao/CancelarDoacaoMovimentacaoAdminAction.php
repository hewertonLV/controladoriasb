<?php

namespace App\Actions\Movimentacoes\Doacao;

use App\Http\Requests\Admin\Movimentacoes\CancelarDoacaoMovimentacaoAdminRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\CancelarDoacaoMovimentacaoAdminService;

final class CancelarDoacaoMovimentacaoAdminAction
{
    public function __construct(
        private readonly CancelarDoacaoMovimentacaoAdminService $service,
    ) {}

    public function __invoke(CancelarDoacaoMovimentacaoAdminRequest $request, Movimentacao $movimentacaoDoacao): void
    {
        $this->service->executar(
            $movimentacaoDoacao,
            $request->user(),
            trim((string) $request->validated('motivo')),
        );
    }
}
