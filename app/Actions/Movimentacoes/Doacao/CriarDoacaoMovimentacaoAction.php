<?php

namespace App\Actions\Movimentacoes\Doacao;

use App\Http\Requests\Admin\Movimentacoes\StoreDoacaoMovimentacaoRequest;
use App\Services\Movimentacoes\DoacaoMovimentacaoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CriarDoacaoMovimentacaoAction
{
    public function __construct(
        private readonly DoacaoMovimentacaoService $doacao,
    ) {}

    /**
     * @return Collection<int, \App\Models\Movimentacao>
     */
    public function __invoke(StoreDoacaoMovimentacaoRequest $request): Collection
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->validated();
        $itens = $payload['itens'] ?? [[
            'id_fruta' => $payload['id_fruta'],
            'qtd_fruta_um' => $payload['qtd_fruta_um'],
        ]];

        unset($payload['itens'], $payload['id_fruta'], $payload['qtd_fruta_um']);

        return DB::transaction(function () use ($payload, $itens, $request): Collection {
            return collect($itens)
                ->map(fn (array $item) => $this->doacao->registrarDoacao(array_merge($payload, $item), $request->user()))
                ->values();
        });
    }
}
