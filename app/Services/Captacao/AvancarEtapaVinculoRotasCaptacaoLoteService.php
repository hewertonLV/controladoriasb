<?php

namespace App\Services\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Models\Captacao\CaptacaoLote;
use Illuminate\Validation\ValidationException;

final class AvancarEtapaVinculoRotasCaptacaoLoteService
{
    public function __construct(
        private readonly CaptacaoLoteService $lotes,
        private readonly PedidoService $pedidos,
    ) {}

    /**
     * @throws ValidationException
     */
    public function concluirManualmente(CaptacaoLote $lote): CaptacaoLote
    {
        if ($lote->status !== CaptacaoLoteStatus::VincularRotasNosPedidos) {
            throw ValidationException::withMessages([
                'status' => 'A conclusão de rotas e carregamento só é permitida nesta etapa do lote.',
            ]);
        }

        $this->pedidos->assertPedidosComQuantidadeTemRota($lote);
        $this->pedidos->assertPedidosComQuantidadeTemOrdemCarregamento($lote);

        return $this->lotes->transicionarStatus($lote, CaptacaoLoteStatus::VincularFreteVenda);
    }
}
