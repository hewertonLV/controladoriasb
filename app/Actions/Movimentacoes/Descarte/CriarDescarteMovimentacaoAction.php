<?php

namespace App\Actions\Movimentacoes\Descarte;

use App\Http\Requests\Admin\Movimentacoes\StoreDescarteMovimentacaoRequest;
use App\Services\Movimentacoes\DescarteMovimentacaoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CriarDescarteMovimentacaoAction
{
    public function __construct(
        private readonly DescarteMovimentacaoService $descarte,
    ) {}

    /**
     * @return Collection<int, \App\Models\Movimentacao>
     */
    public function __invoke(StoreDescarteMovimentacaoRequest $request): Collection
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
                ->map(fn (array $item) => $this->descarte->registrarDescarte(array_merge($payload, $item), $request->user()))
                ->values();
        });
    }
}
