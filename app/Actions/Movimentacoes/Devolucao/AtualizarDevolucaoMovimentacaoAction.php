<?php

namespace App\Actions\Movimentacoes\Devolucao;

use App\Http\Requests\Admin\Movimentacoes\UpdateDevolucaoMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\DevolucaoMovimentacaoService;

final class AtualizarDevolucaoMovimentacaoAction
{
    public function __construct(
        private readonly DevolucaoMovimentacaoService $devolucao,
    ) {}

    public function __invoke(UpdateDevolucaoMovimentacaoRequest $request, Movimentacao $movimentacaoDevolucao): Movimentacao
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        return $this->devolucao->atualizarDevolucao($movimentacaoDevolucao, $payload, $request->user());
    }
}
