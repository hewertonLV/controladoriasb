<?php

namespace App\Actions\Movimentacoes\Transferencia;

use App\Http\Requests\Admin\Movimentacoes\ConfirmarRecebimentoTransferenciaRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\RecebimentoTransferenciaService;

final class ConfirmarRecebimentoTransferenciaAction
{
    public function __construct(
        private readonly RecebimentoTransferenciaService $recebimento,
    ) {}

    public function __invoke(ConfirmarRecebimentoTransferenciaRequest $request, Movimentacao $entrada): void
    {
        $this->recebimento->confirmarRecebimento($entrada, $request->validated());
    }
}
