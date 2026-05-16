<?php

namespace App\Actions\Movimentacoes\Transferencia;

use App\Http\Requests\Admin\Movimentacoes\StoreTransferenciaMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\TransferenciaMovimentacaoService;

final class CriarTransferenciaMovimentacaoAction
{
    public function __construct(
        private readonly TransferenciaMovimentacaoService $transferencias,
    ) {}

    /**
     * @return array{saida: Movimentacao, entrada: Movimentacao}
     */
    public function __invoke(StoreTransferenciaMovimentacaoRequest $request): array
    {
        return $this->transferencias->criarTransferencia($request->validated());
    }
}
