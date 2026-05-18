<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Actions\Movimentacoes\Compra\CancelarCompraMovimentacaoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\CancelarCompraMovimentacaoRequest;
use App\Models\Movimentacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class CancelarCompraMovimentacaoController extends Controller
{
    public function __invoke(
        CancelarCompraMovimentacaoRequest $request,
        Movimentacao $movimentacao,
        CancelarCompraMovimentacaoAction $cancelar,
    ): JsonResponse|RedirectResponse {
        $cancelar($request, $movimentacao);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Compra cancelada administrativamente.']);
        }

        return redirect()
            ->route('admin.movimentacoes.compras.index')
            ->with('success', 'Compra cancelada administrativamente.');
    }
}
