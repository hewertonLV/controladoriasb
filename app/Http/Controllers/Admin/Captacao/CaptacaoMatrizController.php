<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Enums\PedidoOrigem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Captacao\AdicionarLojaMatrizRequest;
use App\Http\Requests\Admin\Captacao\ToggleCaptacaoConcluidaRequest;
use App\Http\Requests\Admin\Captacao\UpdateCaptacaoCelulaRequest;
use App\Http\Requests\Admin\Captacao\UpdateCaptacaoNumeroPedidoRequest;
use App\Http\Requests\Admin\Captacao\UpdateCaptacaoOrdemCarregamentoRequest;
use App\Http\Requests\Admin\Captacao\UpdateCaptacaoRotaMotoristaRequest;
use App\Http\Requests\Admin\Captacao\UpdateCaptacaoRotaPedidoRequest;
use App\Http\Requests\Admin\Captacao\UpdateCaptacaoRotaVeiculoRequest;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoRota;
use App\Models\Cliente;
use App\Services\Captacao\CaptacaoLoteService;
use App\Services\Captacao\CaptacaoMatrizRotasService;
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
        private readonly CaptacaoMatrizRotasService $matrizRotas,
        private readonly ClienteFrutaVinculoService $vinculos,
        private readonly CaptacaoLoteService $lotes,
    ) {}

    public function index(Request $request): View
    {
        $loteId = $request->integer('lote');
        $lote = CaptacaoLote::query()->with(['unidadeGalpao', 'unidadeFaturamento', 'carteira:id,nome'])->findOrFail($loteId);

        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403, UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
        }

        $lote = $this->lotes->sincronizarStatusComFaturamentoFinalizado($lote);

        $lote->load(['pedidos.itens']);
        $matriz = $this->vinculos->dadosMatriz($lote);

        $pedidosPorCliente = $lote->pedidos->keyBy('id_cliente');
        $totaisPorFruta = $this->matrizEstado->totaisPorFruta($lote, $matriz['frutas'], $matriz['clientes']);
        $rotas = $this->matrizRotas->rotasDaCarteira($lote);
        $gruposRotas = $this->matrizRotas->gruposPorLoja(
            $matriz['clientes'],
            $matriz['frutasPorCliente'],
            $pedidosPorCliente,
            $matriz['frutas'],
        );
        $gruposOrdemCarregamento = $this->matrizRotas->gruposOrdemCarregamento($gruposRotas, $rotas);
        $veiculos = $this->matrizRotas->veiculosDisponiveis();

        $abasPermitidas = ['quantidade', 'rotas', 'por-rota'];
        if ($lote->status->exibeAbaArquivoCiganTransferencia()) {
            $abasPermitidas[] = 'arquivo-cigan';
        }

        $aba = $request->string('aba', 'quantidade')->toString();
        if (! in_array($aba, $abasPermitidas, true)) {
            $aba = $lote->status->exibeAbaArquivoCiganTransferencia() ? 'arquivo-cigan' : 'quantidade';
        }

        return view('admin.captacao.matriz.index', [
            'lote' => $lote,
            'clientes' => $matriz['clientes'],
            'frutas' => $matriz['frutas'],
            'frutasPorCliente' => $matriz['frutasPorCliente'],
            'clientesDisponiveis' => $matriz['clientesDisponiveis'],
            'layoutHash' => $matriz['layout_hash'],
            'pedidosPorCliente' => $pedidosPorCliente,
            'totaisPorFruta' => $totaisPorFruta,
            'rotas' => $rotas,
            'gruposRotas' => $gruposRotas,
            'gruposOrdemCarregamento' => $gruposOrdemCarregamento,
            'veiculos' => $veiculos,
            'aba' => $aba,
            'urlRotasCadastro' => $lote->id_captacao_carteira
                ? route('admin.captacao.rotas.create', ['carteira' => $lote->id_captacao_carteira])
                : route('admin.captacao.rotas.create'),
        ]);
    }

    public function toggleConclusao(ToggleCaptacaoConcluidaRequest $request, CaptacaoLote $lote, Cliente $cliente): JsonResponse
    {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403);
        }

        $pedido = $this->pedidos->definirCaptacaoConcluida(
            $lote,
            $cliente->id,
            $request->boolean('captacao_concluida'),
            PedidoOrigem::Web,
            $request->user(),
        );

        return response()->json([
            'ok' => true,
            'captacao_concluida' => $pedido->captacao_concluida,
            'id_cliente' => $cliente->id,
        ]);
    }

    public function updateNumeroPedido(
        UpdateCaptacaoNumeroPedidoRequest $request,
        CaptacaoLote $lote,
        Cliente $cliente,
    ): JsonResponse {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403);
        }

        $numero = $request->validated('numero_pedido');
        $pedido = $this->pedidos->atualizarNumeroPedido(
            $lote,
            $cliente->id,
            is_string($numero) ? $numero : null,
            PedidoOrigem::Web,
            $request->user(),
        );

        return response()->json([
            'ok' => true,
            'id_cliente' => $cliente->id,
            'numero_pedido' => $pedido->numero_pedido,
        ]);
    }

    public function updateRota(
        UpdateCaptacaoRotaPedidoRequest $request,
        CaptacaoLote $lote,
        Cliente $cliente,
    ): JsonResponse {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403);
        }

        $rotaId = $request->validated('id_captacao_rota');
        $pedido = $this->pedidos->atualizarRotaPedido(
            $lote,
            $cliente->id,
            $rotaId !== null ? (int) $rotaId : null,
            PedidoOrigem::Web,
            $request->user(),
        );

        return response()->json([
            'ok' => true,
            'id_cliente' => $cliente->id,
            'id_captacao_rota' => $pedido->id_captacao_rota,
            'ordem_carregamento' => null,
        ]);
    }

    public function updateOrdemCarregamento(
        UpdateCaptacaoOrdemCarregamentoRequest $request,
        CaptacaoLote $lote,
        Cliente $cliente,
    ): JsonResponse {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403);
        }

        $ordem = $request->validated('ordem_carregamento');
        $atualizados = $this->pedidos->atualizarOrdemCarregamento(
            $lote,
            $cliente->id,
            $ordem !== null ? (int) $ordem : null,
            PedidoOrigem::Web,
            $request->user(),
        );

        return response()->json([
            'ok' => true,
            'id_cliente' => $cliente->id,
            'pedidos_rota' => $atualizados,
        ]);
    }

    public function updateMotoristaRota(
        UpdateCaptacaoRotaMotoristaRequest $request,
        CaptacaoLote $lote,
        CaptacaoRota $rota,
    ): JsonResponse {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403);
        }

        $nome = $request->validated('nome_motorista');
        $rota = $this->matrizRotas->atualizarNomeMotorista(
            $lote,
            $rota,
            is_string($nome) ? $nome : null,
        );

        return response()->json([
            'ok' => true,
            'id_captacao_rota' => $rota->id,
            'nome_motorista' => $rota->nome_motorista,
        ]);
    }

    public function updateVeiculoRota(
        UpdateCaptacaoRotaVeiculoRequest $request,
        CaptacaoLote $lote,
        CaptacaoRota $rota,
    ): JsonResponse {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403);
        }

        $veiculoId = $request->validated('id_veiculo');
        $rota = $this->matrizRotas->atualizarVeiculoRota(
            $lote,
            $rota,
            $veiculoId !== null ? (int) $veiculoId : null,
        );

        $veiculo = $rota->veiculo;

        return response()->json([
            'ok' => true,
            'id_captacao_rota' => $rota->id,
            'id_veiculo' => $rota->id_veiculo,
            'veiculo_rotulo' => $veiculo !== null
                ? "{$veiculo->nome} (SBS {$veiculo->id_sbs})"
                : null,
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
