<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Actions\Movimentacoes\EntradaEstoque\CancelarEntradaEstoqueMovimentacaoAdminAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\CancelarEntradaEstoqueMovimentacaoAdminRequest;
use App\Models\Movimentacao;
use Illuminate\Http\RedirectResponse;

final class CancelarEntradaEstoqueMovimentacaoAdminController extends Controller
{
    public function __invoke(
        CancelarEntradaEstoqueMovimentacaoAdminRequest $request,
        Movimentacao $movimentacaoEntradaEstoque,
        CancelarEntradaEstoqueMovimentacaoAdminAction $cancelar,
    ): RedirectResponse {
        $cancelar($request, $movimentacaoEntradaEstoque);

        return redirect()
            ->route('admin.movimentacoes.entradas-estoque.show', $movimentacaoEntradaEstoque)
            ->with('success', 'Entrada de estoque cancelada administrativamente.');
    }
}
