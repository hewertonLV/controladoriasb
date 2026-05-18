<?php

namespace App\Actions\Movimentacoes\Descarte;

use App\Http\Requests\Admin\Movimentacoes\UpdateDescarteMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\DescarteMovimentacaoService;

final class AtualizarDescarteMovimentacaoAction
{
    public function __construct(
        private readonly DescarteMovimentacaoService $descarte,
    ) {}

    public function __invoke(UpdateDescarteMovimentacaoRequest $request, Movimentacao $movimentacaoDescarte): Movimentacao
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        return $this->descarte->atualizarDescarte($movimentacaoDescarte, $payload, $request->user());
    }
}
