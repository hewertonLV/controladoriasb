<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Actions\Movimentacoes\Venda\CancelarVendaMovimentacaoAdminAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\CancelarVendaMovimentacaoAdminRequest;
use App\Models\Movimentacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

final class CancelarVendaMovimentacaoAdminController extends Controller
{
    public function __invoke(
        CancelarVendaMovimentacaoAdminRequest $request,
        Movimentacao $movimentacaoVenda,
        CancelarVendaMovimentacaoAdminAction $cancelar,
    ): JsonResponse|RedirectResponse {
        $cancelar($request, $movimentacaoVenda);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Venda cancelada administrativamente.']);
        }

        return redirect()
            ->route('admin.movimentacoes.vendas.index')
            ->with('success', 'Venda cancelada administrativamente.');
    }
}
