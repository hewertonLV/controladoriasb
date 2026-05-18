<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Actions\Movimentacoes\Venda\CancelarVendaMovimentacaoAdminAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\CancelarVendaMovimentacaoAdminRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\CancelarVendaMovimentacaoAdminService;
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

    public function item(
        CancelarVendaMovimentacaoAdminRequest $request,
        Movimentacao $movimentacaoVenda,
        CancelarVendaMovimentacaoAdminService $service,
    ): JsonResponse|RedirectResponse {
        $service->executarItem(
            $movimentacaoVenda,
            $request->user(),
            trim((string) $request->validated('motivo')),
        );

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Item da venda cancelado administrativamente.']);
        }

        return redirect()
            ->route('admin.movimentacoes.vendas.show', $movimentacaoVenda)
            ->with('success', 'Item da venda cancelado administrativamente.');
    }
}
