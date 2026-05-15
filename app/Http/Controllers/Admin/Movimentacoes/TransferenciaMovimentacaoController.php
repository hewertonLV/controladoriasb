<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Actions\Movimentacoes\Transferencia\RegistrarTransferenciaMovimentacaoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\StoreTransferenciaMovimentacaoRequest;
use Illuminate\Http\JsonResponse;

class TransferenciaMovimentacaoController extends Controller
{
    public function store(StoreTransferenciaMovimentacaoRequest $request, RegistrarTransferenciaMovimentacaoAction $registrar): JsonResponse
    {
        $preview = $registrar($request);

        return response()->json([
            'message' => 'Pré-visualização em memória; persistência na próxima fase.',
            'data' => $preview->only($preview->getFillable()),
        ]);
    }
}
