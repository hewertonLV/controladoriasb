<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Enums\PedidoOrigem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Captacao\SalvarPedidoPorLojaRequest;
use App\Http\Requests\Admin\Captacao\ToggleCaptacaoConcluidaRequest;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Cliente;
use App\Services\Captacao\CaptacaoPrecificacaoService;
use App\Services\Captacao\ClienteFrutaVinculoService;
use App\Services\Captacao\PedidoCaptacaoEstadoService;
use App\Services\Captacao\PedidoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CaptacaoPedidoPorLojaController extends Controller
{
    public function __construct(
        private readonly PedidoService $pedidos,
        private readonly PedidoCaptacaoEstadoService $estado,
        private readonly ClienteFrutaVinculoService $vinculos,
        private readonly CaptacaoPrecificacaoService $precificacao,
    ) {}

    public function carteiras(Request $request): View
    {
        $dataReferencia = $request->string('data_referencia', now()->toDateString())->toString();

        $lotes = CaptacaoLote::query()
            ->with(['carteira:id,nome', 'unidadeGalpao:id,nome', 'unidadeFaturamento:id,nome', 'pedidos'])
            ->where('tipo', CaptacaoLoteTipo::CaptacaoPedidos)
            ->where('status', CaptacaoLoteStatus::CaptacaoEmAndamento)
            ->whereDate('data_referencia', $dataReferencia)
            ->orderBy('id_captacao_carteira')
            ->get()
            ->filter(fn (CaptacaoLote $lote) => app(UnidadeNegocioAccessService::class)
                ->canAccess($request->user(), (int) $lote->id_unidade_negocio_galpao));

        $resumos = $lotes->map(function (CaptacaoLote $lote): array {
            $lote->loadMissing(['pedidos.itens']);
            $elegiveis = $this->estado->lojasDaCarteira($lote);
            $concluidas = $elegiveis->filter(function ($cliente) use ($lote): bool {
                $pedido = $lote->pedidos->firstWhere('id_cliente', $cliente->id);

                return $pedido?->captacao_concluida === true;
            })->count();

            return [
                'lote' => $lote,
                'total_lojas' => $elegiveis->count(),
                'concluidas' => $concluidas,
            ];
        });

        return view('admin.captacao.pedidos-por-loja.carteiras', [
            'dataReferencia' => $dataReferencia,
            'resumos' => $resumos,
        ]);
    }

    public function lojas(Request $request, CaptacaoLote $lote): View
    {
        $this->assertAcessoLote($request, $lote);
        $lote->load(['carteira', 'unidadeGalpao', 'pedidos.itens']);

        $lojas = $this->estado->lojasDaCarteira($lote)->map(function (Cliente $cliente) use ($lote): array {
            $pedido = $lote->pedidos->firstWhere('id_cliente', $cliente->id);
            $estado = $this->estado->estadoLoja($lote, $cliente, $pedido);

            return [
                'cliente' => $cliente,
                'estado' => $estado,
                'possui_frutas' => $this->estado->clientePossuiFrutasVinculadas($cliente),
            ];
        });

        return view('admin.captacao.pedidos-por-loja.lojas', [
            'lote' => $lote,
            'lojas' => $lojas,
        ]);
    }

    public function show(Request $request, CaptacaoLote $lote, Cliente $cliente): View
    {
        $this->assertAcessoLote($request, $lote);
        $this->assertClienteDaCarteira($lote, $cliente);

        $lote->load(['pedidos.itens.fruta', 'unidadeGalpao']);
        $pedidoAtual = $lote->pedidos->firstWhere('id_cliente', $cliente->id);
        $pedidoAnterior = $this->estado->pedidoAnteriorCaptacao($cliente->id, $lote);

        $frutas = app(ClienteFrutaVinculoService::class)->frutasVinculadasDoCliente($cliente->id);
        $possuiFrutas = $frutas->isNotEmpty();

        $linhas = $frutas->map(function ($fruta) use ($lote, $cliente, $pedidoAtual, $pedidoAnterior): array {
            $itemAtual = $pedidoAtual?->itens->firstWhere('id_fruta', $fruta->id);
            $itemAnterior = $pedidoAnterior?->itens->firstWhere('id_fruta', $fruta->id);
            $custo = $this->precificacao->custoReferenciaPorUm(
                (int) $lote->id_unidade_negocio_galpao,
                $fruta,
            );

            return [
                'fruta' => $fruta,
                'item_atual' => $itemAtual,
                'item_anterior' => $itemAnterior,
                'custo' => $custo,
            ];
        });

        $estadoLoja = $this->estado->estadoLoja($lote, $cliente, $pedidoAtual);

        $itensUltimoPedido = $pedidoAnterior === null
            ? collect()
            : $pedidoAnterior->itens
                ->filter(fn ($item): bool => (float) $item->quantidade > 0)
                ->sortBy(fn ($item): string => $item->fruta?->nome ?? '')
                ->values();

        $linhasUltimoPedido = $itensUltimoPedido->map(fn ($item): array => [
            'item' => $item,
            'rentabilidade' => $this->precificacao->detalheRentabilidadeItem($item, (float) $cliente->desconto_nf),
        ]);

        $rentabilidadeUltimoPedido = $itensUltimoPedido->isEmpty()
            ? null
            : $this->precificacao->rentabilidadePedido($itensUltimoPedido, (float) $cliente->desconto_nf);

        return view('admin.captacao.pedidos-por-loja.show', [
            'lote' => $lote,
            'cliente' => $cliente,
            'pedidoAnterior' => $pedidoAnterior,
            'itensUltimoPedido' => $itensUltimoPedido,
            'linhasUltimoPedido' => $linhasUltimoPedido,
            'rentabilidadeUltimoPedido' => $rentabilidadeUltimoPedido,
            'pedidoAtual' => $pedidoAtual,
            'linhas' => $linhas,
            'estadoLoja' => $estadoLoja,
            'podeEditar' => $lote->status === CaptacaoLoteStatus::CaptacaoEmAndamento,
            'possuiFrutas' => $possuiFrutas,
        ]);
    }

    public function salvar(SalvarPedidoPorLojaRequest $request, CaptacaoLote $lote, Cliente $cliente): RedirectResponse
    {
        $this->assertAcessoLote($request, $lote);
        $this->assertClienteDaCarteira($lote, $cliente);

        $dados = $request->pedidoPayload($cliente->id);
        $this->pedidos->salvarPedidoComItens($lote, $dados, PedidoOrigem::Web, $request->user());

        return redirect()
            ->route('admin.captacao.pedidos-por-loja.show', [$lote, $cliente])
            ->with('success', 'Pedido sincronizado.');
    }

    public function toggleConclusao(ToggleCaptacaoConcluidaRequest $request, CaptacaoLote $lote, Cliente $cliente): JsonResponse|RedirectResponse
    {
        $this->assertAcessoLote($request, $lote);
        $this->assertClienteDaCarteira($lote, $cliente);

        $pedido = $this->pedidos->definirCaptacaoConcluida(
            $lote,
            $cliente->id,
            $request->boolean('captacao_concluida'),
            PedidoOrigem::Web,
            $request->user(),
        );

        $lote->load(['pedidos.itens']);
        $estado = $this->estado->estadoLoja($lote, $cliente, $pedido);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'captacao_concluida' => $pedido->captacao_concluida,
                'estado' => $estado,
            ]);
        }

        return back()->with('success', $pedido->captacao_concluida
            ? 'Captação da loja concluída.'
            : 'Captação da loja reaberta.');
    }

    private function assertAcessoLote(Request $request, CaptacaoLote $lote): void
    {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), (int) $lote->id_unidade_negocio_galpao)) {
            abort(403, UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
        }
    }

    private function assertClienteDaCarteira(CaptacaoLote $lote, Cliente $cliente): void
    {
        if ($lote->id_captacao_carteira !== null
            && (int) $cliente->id_captacao_carteira !== (int) $lote->id_captacao_carteira) {
            abort(404);
        }
    }
}
