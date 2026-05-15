<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Actions\Movimentacoes\Descarte\RegistrarDescarteMovimentacaoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\StoreDescarteMovimentacaoRequest;
use Illuminate\Http\JsonResponse;

class DescarteMovimentacaoController extends Controller
{
    public function store(StoreDescarteMovimentacaoRequest $request, RegistrarDescarteMovimentacaoAction $registrar): JsonResponse
    {
        $preview = $registrar($request);

        return response()->json([
            'message' => 'Pré-visualização em memória; persistência na próxima fase.',
            'data' => $preview->only($preview->getFillable()),
        ]);
    }
}
