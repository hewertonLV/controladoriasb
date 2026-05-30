<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Enums\PedidoOrigem;
use App\Actions\Captacao\ConcluirCaptacaoLoteAction;
use App\Http\Controllers\Controller;
use App\Services\Captacao\ConcluirCaptacaoLoteService;
use App\Http\Requests\Admin\Captacao\SalvarPedidoPorLojaRequest;
use App\Http\Requests\Admin\Captacao\ToggleCaptacaoConcluidaRequest;
use App\Http\Requests\Admin\Captacao\UpdateCaptacaoPedidoPorLojaSaidaFisicaVendaRequest;
use App\Models\Captacao\CaptacaoCarteira;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\Pedido;
use App\Models\Cliente;
use App\Services\Captacao\CaptacaoPrecificacaoService;
use App\Services\Captacao\ClienteFrutaVinculoService;
use App\Services\Captacao\PedidoCaptacaoEstadoService;
use App\Services\Captacao\PedidoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use App\Support\Captacao\CaptacaoPedidoPorLojaSaidaFisicaService;
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
        private readonly CaptacaoPedidoPorLojaSaidaFisicaService $saidaFisicaLoja,
        private readonly ConcluirCaptacaoLoteService $concluirCaptacaoLote,
    ) {}

    public function carteiras(Request $request): View
    {
        $dataReferencia = $request->string('data_referencia', now()->toDateString())->toString();

        $lotesQuery = CaptacaoLote::query()
            ->with(['carteira:id,nome', 'unidadeGalpao:id,nome', 'unidadeFaturamento:id,nome', 'pedidos'])
            ->where('tipo', CaptacaoLoteTipo::CaptacaoPedidos)
            ->whereIn('status', [
                CaptacaoLoteStatus::CaptacaoEmAndamento,
                CaptacaoLoteStatus::CaptacaoConcluida,
            ])
            ->whereDate('data_referencia', $dataReferencia)
            ->orderBy('id_captacao_carteira')
            ->get()
            ->filter(fn (CaptacaoLote $lote) => app(UnidadeNegocioAccessService::class)
                ->canAccess($request->user(), (int) $lote->id_unidade_negocio_galpao));

        $mapearResumo = function (CaptacaoLote $lote): array {
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
                'captacao_concluida' => $lote->status === CaptacaoLoteStatus::CaptacaoConcluida,
            ];
        };

        $resumos = $lotesQuery
            ->filter(fn (CaptacaoLote $l) => $l->status === CaptacaoLoteStatus::CaptacaoEmAndamento)
            ->map($mapearResumo)
            ->values();

        $resumosConcluidos = $lotesQuery
            ->filter(fn (CaptacaoLote $l) => $l->status === CaptacaoLoteStatus::CaptacaoConcluida)
            ->map($mapearResumo)
            ->values();

        return view('admin.captacao.pedidos-por-loja.carteiras', [
            'dataReferencia' => $dataReferencia,
            'resumos' => $resumos,
            'resumosConcluidos' => $resumosConcluidos,
            'carteiras' => CaptacaoCarteira::query()
                ->where('ativo', true)
                ->orderBy('nome')
                ->get(['id', 'nome', 'id_unidade_negocio_faturamento', 'id_unidade_negocio_galpao']),
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

        $validacaoConclusaoLote = $this->concluirCaptacaoLote->pendenciasParaConcluir($lote);

        return view('admin.captacao.pedidos-por-loja.lojas', [
            'lote' => $lote,
            'lojas' => $lojas,
            'captacaoLoteConcluida' => $lote->status === CaptacaoLoteStatus::CaptacaoConcluida,
            'podeConcluirCaptacaoLote' => $validacaoConclusaoLote['pode'],
            'pendenciasConclusaoCaptacaoLote' => $validacaoConclusaoLote['pendencias'],
        ]);
    }

    public function concluirCaptacao(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        $this->assertAcessoLote($request, $lote);

        app(ConcluirCaptacaoLoteAction::class)->executar($lote);

        return redirect()
            ->route('admin.captacao.pedidos-por-loja.lojas', $lote)
            ->with('success', 'Captação do lote concluída.');
    }

    public function show(Request $request, CaptacaoLote $lote, Cliente $cliente): View
    {
        $this->assertAcessoLote($request, $lote);
        $this->assertClienteDaCarteira($lote, $cliente);

        $cliente->load(['unidadeSaidaFisicoPadrao:id,nome,id_cigam']);

        $lote->load(['pedidos.itens.fruta', 'unidadeGalpao', 'unidadeFaturamento']);
        $pedidoAtual = $lote->pedidos->firstWhere('id_cliente', $cliente->id);
        $pedidoAnterior = $this->estado->pedidoAnteriorCaptacao($cliente->id, $lote);

        $frutas = app(ClienteFrutaVinculoService::class)->frutasVinculadasDoCliente($cliente->id);
        $possuiFrutas = $frutas->isNotEmpty();

        $idSaidaSelecionada = $this->saidaFisicaLoja->idSaidaEfetivaParaExibicao(
            $pedidoAtual ?? new Pedido(['id_unidade_negocio_saida_venda' => null]),
            $lote,
            $cliente,
        );
        $dataReferenciaCusto = $lote->data_referencia?->copy()->startOfDay();

        $linhas = $frutas->map(function ($fruta) use (
            $lote,
            $pedidoAtual,
            $pedidoAnterior,
            $idSaidaSelecionada,
            $dataReferenciaCusto,
        ): array {
            $itemAtual = $pedidoAtual?->itens->firstWhere('id_fruta', $fruta->id);
            $itemAnterior = $pedidoAnterior?->itens->firstWhere('id_fruta', $fruta->id);
            $custoDetalhe = $this->precificacao->detalheCustoSaidaFisica(
                $idSaidaSelecionada,
                (int) $lote->id_unidade_negocio_faturamento,
                $fruta,
                $dataReferenciaCusto,
            );

            return [
                'fruta' => $fruta,
                'item_atual' => $itemAtual,
                'item_anterior' => $itemAnterior,
                'custo' => $custoDetalhe['custo_final'],
                'custo_detalhe' => $custoDetalhe,
            ];
        });

        $estadoLoja = $this->estado->estadoLoja($lote, $cliente, $pedidoAtual);

        $captacaoLoteAberta = $lote->status === CaptacaoLoteStatus::CaptacaoEmAndamento;
        $pedidoConcluido = (bool) ($pedidoAtual?->captacao_concluida ?? false);

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

        $opcoesSaidaFisica = $this->saidaFisicaLoja->opcoesParaLote($lote);
        $saidaPadraoCadastroId = $cliente->id_unidade_negocio_saida_fisico_padrao;
        $saidaPadraoCadastroLabel = $saidaPadraoCadastroId !== null
            ? $this->saidaFisicaLoja->labelUnidadePorId($lote, (int) $saidaPadraoCadastroId)
                ?? $cliente->unidadeSaidaFisicoPadrao?->nome
            : null;

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
            'captacaoLoteAberta' => $captacaoLoteAberta,
            'pedidoConcluido' => $pedidoConcluido,
            'podeEditar' => $captacaoLoteAberta && ! $pedidoConcluido,
            'possuiFrutas' => $possuiFrutas,
            'opcoesSaidaFisica' => $opcoesSaidaFisica,
            'idSaidaSelecionada' => $idSaidaSelecionada,
            'saidaPadraoCadastroId' => $saidaPadraoCadastroId,
            'saidaPadraoCadastroLabel' => $saidaPadraoCadastroLabel,
            'pedidoSaidaOverride' => $pedidoAtual?->id_unidade_negocio_saida_venda !== null,
        ]);
    }

    public function updateSaidaFisicaVenda(
        UpdateCaptacaoPedidoPorLojaSaidaFisicaVendaRequest $request,
        CaptacaoLote $lote,
        Cliente $cliente,
    ): JsonResponse {
        $this->assertAcessoLote($request, $lote);
        $this->assertLoteEditavel($lote);
        $this->assertClienteDaCarteira($lote, $cliente);

        if ($lote->status !== CaptacaoLoteStatus::CaptacaoEmAndamento) {
            abort(422, 'A captação deste lote não está em andamento.');
        }

        $pedido = $this->pedidos->atualizarSaidaFisicaVendaPedidoPorLoja(
            $lote,
            $cliente->id,
            (int) $request->validated('id_unidade_negocio_saida_venda'),
            PedidoOrigem::Web,
            $request->user(),
        );

        $idSaida = (int) $pedido->id_unidade_negocio_saida_venda;
        $dataReferencia = $lote->data_referencia?->copy()->startOfDay();
        $custos = [];

        foreach ($this->vinculos->frutasVinculadasDoCliente($cliente->id) as $fruta) {
            $detalhe = $this->precificacao->detalheCustoSaidaFisica(
                $idSaida,
                (int) $lote->id_unidade_negocio_faturamento,
                $fruta,
                $dataReferencia,
            );
            $custos[(int) $fruta->id] = $this->precificacao->detalheCustoSaidaFisicaParaApi($detalhe);
        }

        return response()->json([
            'ok' => true,
            'id_unidade_negocio_saida_venda' => $pedido->id_unidade_negocio_saida_venda,
            'custos' => $custos,
        ]);
    }

    public function salvar(SalvarPedidoPorLojaRequest $request, CaptacaoLote $lote, Cliente $cliente): JsonResponse|RedirectResponse
    {
        $this->assertAcessoLote($request, $lote);
        $this->assertLoteEditavel($lote);
        $this->assertClienteDaCarteira($lote, $cliente);

        $dados = $request->pedidoPayload($cliente->id);
        $pedido = $this->pedidos->salvarPedidoComItens($lote, $dados, PedidoOrigem::Web, $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'pedido_id' => $pedido->id,
            ]);
        }

        return redirect()
            ->route('admin.captacao.pedidos-por-loja.show', [$lote, $cliente])
            ->with('success', 'Pedido salvo.');
    }

    public function toggleConclusao(ToggleCaptacaoConcluidaRequest $request, CaptacaoLote $lote, Cliente $cliente): JsonResponse|RedirectResponse
    {
        $this->assertAcessoLote($request, $lote);
        $this->assertLoteEditavel($lote);
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

    private function assertLoteEditavel(CaptacaoLote $lote): void
    {
        if ($lote->status !== CaptacaoLoteStatus::CaptacaoEmAndamento) {
            abort(403, 'Captação concluída. Não é possível alterar ou reabrir.');
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
