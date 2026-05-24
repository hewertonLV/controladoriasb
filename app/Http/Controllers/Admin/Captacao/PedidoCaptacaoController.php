<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Enums\PedidoOrigem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Captacao\StorePedidoCaptacaoRequest;
use App\Models\Captacao\CaptacaoLote;
use App\Services\Captacao\PedidoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
class PedidoCaptacaoController extends Controller
{
    public function __construct(
        private readonly PedidoService $pedidos,
    ) {}

    public function store(StorePedidoCaptacaoRequest $request, CaptacaoLote $lote): RedirectResponse
    {
        if (! $this->canAccessLote($request, $lote)) {
            abort(403, UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
        }

        $this->pedidos->salvarPedidoComItens(
            $lote,
            $request->validated(),
            PedidoOrigem::Web,
            $request->user(),
        );

        return back()->with('success', 'Pedido registrado na captação.');
    }

    private function canAccessLote(Request $request, CaptacaoLote $lote): bool
    {
        return app(UnidadeNegocioAccessService::class)
            ->canAccess($request->user(), $lote->id_unidade_negocio_galpao);
    }
}
