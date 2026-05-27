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

    public function tentarAvancarAutomaticamente(CaptacaoLote $lote): CaptacaoLote
    {
        if ($lote->status !== CaptacaoLoteStatus::VincularRotasNosPedidos) {
            return $lote;
        }

        if ($this->pedidos->lotePossuiPedidoComQuantidadeSemRota($lote)) {
            return $lote->fresh();
        }

        return $this->lotes->transicionarStatus($lote, CaptacaoLoteStatus::VendasFinalizadas);
    }

    /**
     * @throws ValidationException
     */
    public function concluirManualmente(CaptacaoLote $lote): CaptacaoLote
    {
        if ($lote->status !== CaptacaoLoteStatus::VincularRotasNosPedidos) {
            throw ValidationException::withMessages([
                'status' => 'A conclusão do vínculo de rotas só é permitida nesta etapa do lote.',
            ]);
        }

        $this->pedidos->assertPedidosComQuantidadeTemRota($lote);

        return $this->lotes->transicionarStatus($lote, CaptacaoLoteStatus::VendasFinalizadas);
    }
}
