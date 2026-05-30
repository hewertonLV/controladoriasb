<?php

namespace App\Actions\Movimentacoes\Transferencia;

use App\Http\Requests\Admin\Movimentacoes\ConfirmarRecebimentoTransferenciaRequest;
use App\Services\Captacao\CaptacaoDemandaRotaService;
use App\Services\Captacao\EfetivarVendasPendentesCaptacaoRotaService;
use App\Services\Movimentacoes\TransferenciaMovimentacaoService;
use InvalidArgumentException;

final class ConfirmarRecebimentoTransferenciaAction
{
    public function __construct(
        private readonly TransferenciaMovimentacaoService $transferencias,
        private readonly EfetivarVendasPendentesCaptacaoRotaService $efetivarVendasCaptacao,
        private readonly CaptacaoDemandaRotaService $demandasRota,
    ) {}

    public function __invoke(ConfirmarRecebimentoTransferenciaRequest $request, int $transferenciaOrigemId): void
    {
        try {
            $this->demandasRota->marcarTransferenciaIniciada($transferenciaOrigemId);
            $this->transferencias->confirmarRecebimentoConforme($transferenciaOrigemId);
            $this->demandasRota->marcarTransferenciaConcluida($transferenciaOrigemId);
        } catch (InvalidArgumentException $e) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'transferencia' => $e->getMessage(),
            ]);
        }

        $this->efetivarVendasCaptacao->tentarEfetivarPorTransferencia($transferenciaOrigemId, $request->user());
    }
}
