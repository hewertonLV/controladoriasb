<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Actions\Movimentacoes\Devolucao\RegistrarDevolucaoMovimentacaoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\StoreDevolucaoMovimentacaoRequest;
use Illuminate\Http\JsonResponse;

class DevolucaoMovimentacaoController extends Controller
{
    public function store(StoreDevolucaoMovimentacaoRequest $request, RegistrarDevolucaoMovimentacaoAction $registrar): JsonResponse
    {
        $preview = $registrar($request);

        return response()->json([
            'message' => 'Pré-visualização em memória; persistência na próxima fase.',
            'data' => $preview->only($preview->getFillable()),
        ]);
    }
}
