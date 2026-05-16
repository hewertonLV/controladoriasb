<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Actions\Movimentacoes\Transferencia\ConfirmarRecebimentoTransferenciaAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\ConfirmarRecebimentoTransferenciaRequest;
use App\Models\Movimentacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class RecebimentoTransferenciaController extends Controller
{
    public function store(
        ConfirmarRecebimentoTransferenciaRequest $request,
        Movimentacao $transferenciaOrigem,
        ConfirmarRecebimentoTransferenciaAction $confirmar,
    ): JsonResponse|RedirectResponse {
        $entrada = $transferenciaOrigem->movimentacaoPareada;
        abort_if($entrada === null, 404);

        $confirmar($request, $entrada);

        $anchor = (int) $transferenciaOrigem->transferencia_origem_id;

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Recebimento registrado.']);
        }

        return redirect()
            ->route('admin.movimentacoes.transferencias.show', ['transferenciaOrigem' => $anchor])
            ->with('success', 'Conferência de recebimento registrada.');
    }
}
