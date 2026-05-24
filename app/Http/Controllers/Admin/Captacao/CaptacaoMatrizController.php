<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Enums\PedidoOrigem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Captacao\AdicionarLojaMatrizRequest;
use App\Http\Requests\Admin\Captacao\UpdateCaptacaoCelulaRequest;
use App\Models\Captacao\CaptacaoLote;
use App\Services\Captacao\CaptacaoMatrizEstadoService;
use App\Services\Captacao\ClienteFrutaVinculoService;
use App\Services\Captacao\PedidoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CaptacaoMatrizController extends Controller
{
    public function __construct(
        private readonly PedidoService $pedidos,
        private readonly CaptacaoMatrizEstadoService $matrizEstado,
        private readonly ClienteFrutaVinculoService $vinculos,
    ) {}

    public function index(Request $request): View
    {
        $loteId = $request->integer('lote');
        $lote = CaptacaoLote::query()->with(['unidadeGalpao', 'unidadeFaturamento'])->findOrFail($loteId);

        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403, UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
        }

        $lote->load(['pedidos.itens']);
        $matriz = $this->vinculos->dadosMatriz($lote);

        return view('admin.captacao.matriz.index', [
            'lote' => $lote,
            'clientes' => $matriz['clientes'],
            'frutas' => $matriz['frutas'],
            'frutasPorCliente' => $matriz['frutasPorCliente'],
            'clientesDisponiveis' => $matriz['clientesDisponiveis'],
            'layoutHash' => $matriz['layout_hash'],
        ]);
    }

    public function adicionarLoja(AdicionarLojaMatrizRequest $request, CaptacaoLote $lote): JsonResponse
    {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403);
        }

        $cliente = $this->vinculos->assertClienteElegivelParaMatriz(
            $lote,
            (int) $request->validated('id_cliente'),
        );

        $this->pedidos->adicionarLojaNaMatriz($lote, $cliente, PedidoOrigem::Web, $request->user());

        $lote->refresh()->load(['pedidos.itens']);
        $matriz = $this->vinculos->dadosMatriz($lote);

        return response()->json([
            'ok' => true,
            'redirect' => route('admin.captacao.matriz.index', ['lote' => $lote->id]),
            'layout_hash' => $matriz['layout_hash'],
        ]);
    }

    public function estado(Request $request, CaptacaoLote $lote): JsonResponse
    {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403);
        }

        return response()->json($this->matrizEstado->snapshot($lote->fresh()));
    }

    public function updateCelula(UpdateCaptacaoCelulaRequest $request, CaptacaoLote $lote): JsonResponse
    {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403);
        }

        $item = $this->pedidos->upsertCelulaMatriz(
            $lote,
            (int) $request->validated('id_cliente'),
            $request->validated(),
            PedidoOrigem::Web,
            $request->user(),
        );

        return response()->json([
            'ok' => true,
            'item' => [
                'id' => $item->id,
                'version' => $item->version,
                'quantidade' => $item->quantidade,
                'preco_venda' => $item->preco_venda,
                'custo_referencia' => $item->custo_referencia,
            ],
        ]);
    }
}
