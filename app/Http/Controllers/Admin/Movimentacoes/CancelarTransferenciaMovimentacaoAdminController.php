<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Actions\Movimentacoes\Transferencia\CancelarTransferenciaMovimentacaoAdminAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\CancelarTransferenciaMovimentacaoAdminRequest;
use App\Models\Movimentacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class CancelarTransferenciaMovimentacaoAdminController extends Controller
{
    public function __invoke(
        CancelarTransferenciaMovimentacaoAdminRequest $request,
        Movimentacao $transferenciaOrigem,
        CancelarTransferenciaMovimentacaoAdminAction $cancelar,
    ): JsonResponse|RedirectResponse {
        $cancelar($request, $transferenciaOrigem);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Transferência cancelada administrativamente.']);
        }

        return redirect()
            ->route('admin.movimentacoes.transferencias.index')
            ->with('success', 'Transferência cancelada administrativamente.');
    }
}
