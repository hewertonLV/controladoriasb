<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Actions\Movimentacoes\Doacao\CancelarDoacaoMovimentacaoAdminAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\CancelarDoacaoMovimentacaoAdminRequest;
use App\Models\Movimentacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class CancelarDoacaoMovimentacaoAdminController extends Controller
{
    public function __invoke(
        CancelarDoacaoMovimentacaoAdminRequest $request,
        Movimentacao $movimentacaoDoacao,
        CancelarDoacaoMovimentacaoAdminAction $cancelar,
    ): JsonResponse|RedirectResponse {
        $cancelar($request, $movimentacaoDoacao);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Doação cancelada administrativamente.']);
        }

        return redirect()
            ->route('admin.movimentacoes.doacoes.index')
            ->with('success', 'Doação cancelada administrativamente.');
    }
}
