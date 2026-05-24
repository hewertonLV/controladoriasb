<?php

namespace App\Http\Controllers\Api\V1\Captacao;

use App\Enums\PedidoOrigem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Captacao\StorePedidoCaptacaoRequest;
use App\Models\Captacao\CaptacaoLote;
use App\Services\Captacao\PedidoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PedidoApiController extends Controller
{
    public function __construct(
        private readonly PedidoService $pedidos,
    ) {}

    public function store(StorePedidoCaptacaoRequest $request, CaptacaoLote $lote): JsonResponse
    {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403);
        }

        $pedido = $this->pedidos->salvarPedidoComItens(
            $lote,
            $request->validated(),
            PedidoOrigem::App,
            $request->user(),
        );

        return response()->json(['pedido' => $pedido], 201);
    }

    public function meusPedidos(Request $request, CaptacaoLote $lote): JsonResponse
    {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403);
        }

        $pedidos = $lote->pedidos()
            ->with(['itens.fruta', 'cliente:id,razao_social,fantasia'])
            ->when($request->user(), fn ($q) => $q->where('created_by_user_id', $request->user()->id))
            ->get();

        return response()->json(['data' => $pedidos]);
    }
}
