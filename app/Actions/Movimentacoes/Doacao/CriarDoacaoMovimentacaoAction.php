<?php

namespace App\Actions\Movimentacoes\Doacao;

use App\Http\Requests\Admin\Movimentacoes\StoreDoacaoMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\DoacaoMovimentacaoService;

final class CriarDoacaoMovimentacaoAction
{
    public function __construct(
        private readonly DoacaoMovimentacaoService $doacao,
    ) {}

    public function __invoke(StoreDoacaoMovimentacaoRequest $request): Movimentacao
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        return $this->doacao->registrarDoacao($payload, $request->user());
    }
}
