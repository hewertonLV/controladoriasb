<?php

namespace App\Actions\Movimentacoes\Descarte;

use App\Http\Requests\Admin\Movimentacoes\StoreDescarteMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\DescarteMovimentacaoService;

final class CriarDescarteMovimentacaoAction
{
    public function __construct(
        private readonly DescarteMovimentacaoService $descarte,
    ) {}

    public function __invoke(StoreDescarteMovimentacaoRequest $request): Movimentacao
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->validated();

        return $this->descarte->registrarDescarte($payload, $request->user());
    }
}
