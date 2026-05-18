<?php

namespace App\Actions\Movimentacoes\Transferencia;

use App\Http\Requests\Admin\Movimentacoes\CancelarTransferenciaMovimentacaoAdminRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\CancelarTransferenciaMovimentacaoAdminService;

final class CancelarTransferenciaMovimentacaoAdminAction
{
    public function __construct(
        private readonly CancelarTransferenciaMovimentacaoAdminService $service,
    ) {}

    public function __invoke(CancelarTransferenciaMovimentacaoAdminRequest $request, Movimentacao $transferenciaOrigem): void
    {
        $anchor = (int) $transferenciaOrigem->transferencia_origem_id;

        $this->service->executar(
            $anchor,
            $request->user(),
            trim((string) $request->validated('motivo')),
        );
    }
}
