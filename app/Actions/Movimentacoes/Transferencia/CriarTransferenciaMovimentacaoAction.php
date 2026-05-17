<?php

namespace App\Actions\Movimentacoes\Transferencia;

use App\Http\Requests\Admin\Movimentacoes\StoreTransferenciaMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\TransferenciaMovimentacaoService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CriarTransferenciaMovimentacaoAction
{
    public function __construct(
        private readonly TransferenciaMovimentacaoService $transferencias,
    ) {}

    /**
     * @return Collection<int, array{saida: Movimentacao, entrada: Movimentacao}>
     */
    public function __invoke(StoreTransferenciaMovimentacaoRequest $request): Collection
    {
        $payload = $request->validated();
        $itens = $payload['itens'] ?? [[
            'id_fruta' => $payload['id_fruta'],
            'qtd_fruta_um' => $payload['qtd_fruta_um'],
        ]];

        unset($payload['itens'], $payload['id_fruta'], $payload['qtd_fruta_um']);

        return DB::transaction(function () use ($payload, $itens): Collection {
            return collect($itens)
                ->map(fn (array $item) => $this->transferencias->criarTransferencia(array_merge($payload, $item)))
                ->values();
        });
    }
}
