<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Actions\Movimentacoes\Venda\RegistrarVendaMovimentacaoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\StoreVendaMovimentacaoRequest;
use Illuminate\Http\JsonResponse;

class VendaMovimentacaoController extends Controller
{
    public function store(StoreVendaMovimentacaoRequest $request, RegistrarVendaMovimentacaoAction $registrar): JsonResponse
    {
        $preview = $registrar($request);

        return response()->json([
            'message' => 'Pré-visualização em memória; persistência na próxima fase.',
            'data' => $preview->only($preview->getFillable()),
        ]);
    }
}
