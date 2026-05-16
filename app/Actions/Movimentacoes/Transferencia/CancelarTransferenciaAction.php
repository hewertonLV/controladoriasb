<?php

namespace App\Actions\Movimentacoes\Transferencia;

use App\Http\Requests\Admin\Movimentacoes\CancelarTransferenciaRequest;
use App\Services\Movimentacoes\TransferenciaMovimentacaoService;

final class CancelarTransferenciaAction
{
    public function __construct(
        private readonly TransferenciaMovimentacaoService $transferencias,
    ) {}

    public function __invoke(CancelarTransferenciaRequest $request, int $transferenciaOrigemId): void
    {
        $motivo = $request->validated()['motivo_substituicao'] ?? null;
        $this->transferencias->cancelarTransferencia($transferenciaOrigemId, $motivo !== null ? (string) $motivo : null);
    }
}
