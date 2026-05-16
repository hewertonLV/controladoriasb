<?php

namespace App\Actions\Movimentacoes\Doacao;

use App\Http\Requests\Admin\Movimentacoes\UpdateDoacaoMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\DoacaoMovimentacaoService;

final class AtualizarDoacaoMovimentacaoAction
{
    public function __construct(
        private readonly DoacaoMovimentacaoService $doacao,
    ) {}

    public function __invoke(UpdateDoacaoMovimentacaoRequest $request, Movimentacao $movimentacaoDoacao): Movimentacao
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        return $this->doacao->atualizarDoacao($movimentacaoDoacao, $payload, $request->user());
    }
}
