<?php

namespace App\Actions\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Models\Captacao\CaptacaoLote;
use App\Models\User;
use App\Services\Captacao\CaptacaoLoteService;
use App\Services\Captacao\GerarVendasCaptacaoLoteService;
use App\Services\Captacao\PedidoService;
use Illuminate\Validation\ValidationException;

final class FinalizarVendasLoteAction
{
    public function __construct(
        private readonly CaptacaoLoteService $lotes,
        private readonly GerarVendasCaptacaoLoteService $gerarVendas,
        private readonly PedidoService $pedidos,
    ) {}

    public function executar(CaptacaoLote $lote, ?User $user = null): CaptacaoLote
    {
        if ($lote->status !== CaptacaoLoteStatus::FaturamentoCiganIniciado) {
            throw ValidationException::withMessages([
                'status' => 'Inicie o faturamento Cigan antes de finalizar vendas.',
            ]);
        }

        $this->pedidos->assertPedidosComQuantidadeTemRota($lote);

        $this->gerarVendas->executar($lote, $user);

        return $this->lotes->transicionarStatus($lote, CaptacaoLoteStatus::VendasFinalizadas);
    }
}
