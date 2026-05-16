<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Actions\Movimentacoes\Descarte\CancelarDescarteMovimentacaoAdminAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\CancelarDescarteMovimentacaoAdminRequest;
use App\Models\Movimentacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class CancelarDescarteMovimentacaoAdminController extends Controller
{
    public function __invoke(
        CancelarDescarteMovimentacaoAdminRequest $request,
        Movimentacao $movimentacaoDescarte,
        CancelarDescarteMovimentacaoAdminAction $cancelar,
    ): JsonResponse|RedirectResponse {
        $cancelar($request, $movimentacaoDescarte);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Descarte cancelado administrativamente.']);
        }

        return redirect()
            ->route('admin.movimentacoes.descartes.index')
            ->with('success', 'Descarte cancelado administrativamente.');
    }
}
