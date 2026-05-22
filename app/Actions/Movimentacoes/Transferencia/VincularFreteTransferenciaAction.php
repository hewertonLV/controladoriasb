<?php

namespace App\Actions\Movimentacoes\Transferencia;

use App\Http\Requests\Admin\Movimentacoes\VincularFreteTransferenciaRequest;
use App\Services\Movimentacoes\TransferenciaMovimentacaoService;
final class VincularFreteTransferenciaAction
{
    public function __construct(
        private readonly TransferenciaMovimentacaoService $transferencias,
    ) {}

    public function __invoke(VincularFreteTransferenciaRequest $request, int $transferenciaOrigemId): void
    {
        $this->transferencias->vincularFrete($transferenciaOrigemId, $request->validated());
    }
}
