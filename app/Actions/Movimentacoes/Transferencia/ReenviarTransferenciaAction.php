<?php

namespace App\Actions\Movimentacoes\Transferencia;

use App\Http\Requests\Admin\Movimentacoes\ReenviarTransferenciaRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\TransferenciaMovimentacaoService;

final class ReenviarTransferenciaAction
{
    public function __construct(
        private readonly TransferenciaMovimentacaoService $transferencias,
    ) {}

    /**
     * @return array{saida: Movimentacao, entrada: Movimentacao}
     */
    public function __invoke(ReenviarTransferenciaRequest $request, int $transferenciaOrigemId): array
    {
        return $this->transferencias->reenviarAposDivergencia($transferenciaOrigemId, $request->validated());
    }
}
