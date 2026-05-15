<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Actions\Movimentacoes\Doacao\RegistrarDoacaoMovimentacaoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\StoreDoacaoMovimentacaoRequest;
use Illuminate\Http\JsonResponse;

class DoacaoMovimentacaoController extends Controller
{
    public function store(StoreDoacaoMovimentacaoRequest $request, RegistrarDoacaoMovimentacaoAction $registrar): JsonResponse
    {
        $preview = $registrar($request);

        return response()->json([
            'message' => 'Pré-visualização em memória; persistência na próxima fase.',
            'data' => $preview->only($preview->getFillable()),
        ]);
    }
}
