<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Enums\CaptacaoLoteStatus;
use App\Enums\PedidoOrigem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Captacao\AdicionarLojaMatrizRequest;
use App\Http\Requests\Admin\Captacao\RemoverLojaMatrizRequest;
use App\Http\Requests\Admin\Captacao\ToggleCaptacaoConcluidaRequest;
use App\Http\Requests\Admin\Captacao\UpdateCaptacaoCelulaRequest;
use App\Http\Requests\Admin\Captacao\UpdateCaptacaoNumeroPedidoRequest;
use App\Http\Requests\Admin\Captacao\UpdateCaptacaoOrdemCarregamentoRequest;
use App\Http\Requests\Admin\Captacao\UpdateCaptacaoRotaMotoristaRequest;
use App\Http\Requests\Admin\Captacao\UpdateCaptacaoRotaPedidoRequest;
use App\Http\Requests\Admin\Captacao\UpdateCaptacaoRotaVeiculoRequest;
use App\Http\Requests\Admin\Captacao\UpdateCaptacaoSaidaFisicaVendaRequest;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoRota;
use App\Models\Cliente;
use App\Models\UnidadeNegocio;
use App\Actions\Captacao\ConcluirCaptacaoLoteAction;
use App\Services\Captacao\CaptacaoLoteFreteService;
use App\Services\Captacao\CaptacaoLoteService;
use App\Services\Captacao\ConcluirCaptacaoLoteService;
use App\Services\Captacao\GerarVendasCaptacaoLoteService;
use App\Services\Captacao\CaptacaoMatrizRotasService;
use App\Services\Captacao\CaptacaoMatrizEstadoService;
use App\Services\Captacao\ClienteFrutaVinculoService;
use App\Services\Captacao\PedidoService;
use App\Services\Captacao\RomaneioAbastecimentoService;
use App\Services\Captacao\RomaneioCarregamentoService;
use App\Services\Captacao\RomaneioRotaPdfService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use App\Support\Captacao\CaptacaoPedidoPorLojaSaidaFisicaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CaptacaoMatrizController extends Controller
{
    public function __construct(
        private readonly PedidoService $pedidos,
        private readonly CaptacaoMatrizEstadoService $matrizEstado,
        private readonly CaptacaoMatrizRotasService $matrizRotas,
        private readonly ClienteFrutaVinculoService $vinculos,
        private readonly CaptacaoLoteService $lotes,
        private readonly CaptacaoLoteFreteService $freteLote,
        private readonly ConcluirCaptacaoLoteService $concluirCaptacaoLote,
    ) {}

    public function index(Request $request): View
    {
        $loteId = $request->integer('lote');
        $lote = CaptacaoLote::query()->with([
            'unidadeGalpao',
            'unidadeFaturamento.clientePrincipal:id,id_cigam,razao_social',
            'unidadeHubOrigem:id,nome,id_cigam',
            'carteira:id,nome',
        ])->findOrFail($loteId);

        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403, UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
        }

        $lote = $this->lotes->sincronizarStatusComFaturamentoFinalizado($lote);

        $lote->load(['pedidos.itens', 'pedidos.cliente:id,id_unidade_negocio_saida_fisico_padrao']);
        $matriz = $this->vinculos->dadosMatriz($lote);

        $pedidosPorCliente = $lote->pedidos->keyBy('id_cliente');
        $totaisPorFruta = $this->matrizEstado->totaisPorFruta($lote, $matriz['frutas'], $matriz['clientes']);
        $rotas = $this->matrizRotas->rotasDaCarteira($lote);
        $gruposRotas = $this->matrizRotas->gruposPorLoja(
            $lote,
            $matriz['clientes'],
            $matriz['frutasPorCliente'],
            $pedidosPorCliente,
            $matriz['frutas'],
        );
        $gruposOrdemCarregamento = $this->matrizRotas->gruposOrdemCarregamento($gruposRotas, $rotas, $lote);
        $veiculos = $this->matrizRotas->veiculosDisponiveis();

        $abasRotasVinculadas = array_map(
            static fn (array $grupo): string => 'rota-'.$grupo['id_captacao_rota'],
            $gruposOrdemCarregamento,
        );

        $abasPermitidas = array_merge(['quantidade', 'rotas'], $abasRotasVinculadas);
        if ($lote->status->exibeAbaArquivoCigan()) {
            $abasPermitidas[] = 'arquivo-cigan';
        }
        if ($lote->status->exibeAbaFreteHub()) {
            $abasPermitidas[] = 'frete-hub';
        }
        if ($lote->status->exibeAbaFreteVendas()) {
            $abasPermitidas[] = 'frete-vendas';
        }
        if ($lote->status->exibeAbaSaidaEstoqueFisico()) {
            $abasPermitidas[] = 'saida-estoque-fisico';
        }

        $aba = $request->string('aba', 'quantidade')->toString();
        if ($aba === 'frete') {
            $aba = 'frete-hub';
        }
        if ($aba === 'por-rota') {
            $aba = $abasRotasVinculadas[0] ?? 'quantidade';
        }
        if (! in_array($aba, $abasPermitidas, true)) {
            $aba = match (true) {
                $lote->status->exibeAbaSaidaEstoqueFisico() => 'saida-estoque-fisico',
                $lote->status->exibeAbaFreteHub() => 'frete-hub',
                $lote->status->exibeAbaFreteVendas() => 'frete-vendas',
                $lote->status->exibeAbaArquivoCigan() => 'arquivo-cigan',
                default => 'quantidade',
            };
        }

        $hubsDisponiveis = UnidadeNegocio::query()
            ->where('is_hub', true)
            ->where('status', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'id_cigam']);

        $romaneioAbastecimento = $lote->status->exibeAbaArquivoCiganTransferencia()
            ? app(RomaneioAbastecimentoService::class)->preview($lote)
            : collect();

        $dadosFreteHub = $lote->status->exibeAbaFreteHub()
            ? $this->freteLote->dadosFreteHub($lote)
            : null;

        $dadosFreteVendas = $lote->status->exibeAbaFreteVendas()
            ? $this->freteLote->dadosFreteVendas($lote)
            : null;
        $freteVendaEditavel = $lote->status->exibeAbaFreteVendas()
            && $this->freteLote->podeAlterarFreteVenda($lote, $request->user());

        $resumoVendasLote = $lote->status->exibeAbaArquivoCiganVendas()
            ? app(GerarVendasCaptacaoLoteService::class)->resumoVendasLote($lote)
            : [];

        $romaneioCarregamento = collect();
        $romaneioCarregamentoTotaisGerais = null;
        $romaneiosCarregamentoPorRota = collect();
        if ($lote->status->exibeAbaSaidaEstoqueFisico()) {
            $romaneiosCarregamentoPorRota = app(RomaneioCarregamentoService::class)->previewPorRotas($lote);
            $romaneioCarregamento = $romaneiosCarregamentoPorRota
                ->flatMap(fn (array $grupo) => $grupo['lojas'])
                ->values();
            $romaneioCarregamentoTotaisGerais = app(RomaneioCarregamentoService::class)
                ->totaisGerais($romaneioCarregamento);
        }

        $saidaFisicaLoja = app(CaptacaoPedidoPorLojaSaidaFisicaService::class);
        $opcoesSaidaFisicaMatriz = $lote->status === CaptacaoLoteStatus::CaptacaoEmAndamento
            ? $saidaFisicaLoja->opcoesParaLote($lote)
            : [];

        return view('admin.captacao.matriz.index', [
            'lote' => $lote,
            'opcoesSaidaFisicaMatriz' => $opcoesSaidaFisicaMatriz,
            'saidaFisicaLoja' => $saidaFisicaLoja,
            'hubsDisponiveis' => $hubsDisponiveis,
            'romaneioAbastecimento' => $romaneioAbastecimento,
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
            'dadosFreteHub' => $dadosFreteHub,
            'dadosFreteVendas' => $dadosFreteVendas,
            'freteVendaEditavel' => $freteVendaEditavel,
            'resumoVendasLote' => $resumoVendasLote,
            'romaneioCarregamento' => $romaneioCarregamento,
            'romaneioCarregamentoTotaisGerais' => $romaneioCarregamentoTotaisGerais,
            'romaneiosCarregamentoPorRota' => $romaneiosCarregamentoPorRota,
            'urlRotasCadastro' => $lote->id_captacao_carteira
                ? route('admin.captacao.rotas.create', ['carteira' => $lote->id_captacao_carteira])
                : route('admin.captacao.rotas.create'),
            'todasRotasDoLoteConcluidas' => $this->matrizRotas->todasRotasComPedidoEstaoConcluidasNoLote($lote),
            'rotasPendentesConclusaoCaptacao' => $this->matrizRotas->nomesRotasComPedidoNaoConcluidasNoLote($lote),
            ...$this->dadosConclusaoCaptacaoLote($lote),
        ]);
    }

    public function concluirCaptacao(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403);
        }

        app(ConcluirCaptacaoLoteAction::class)->executar($lote);

        return redirect()
            ->route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => 'quantidade'])
            ->with('success', 'Captação do lote concluída.');
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
        $config = $this->matrizRotas->atualizarNomeMotorista(
            $lote,
            $rota,
            is_string($nome) ? $nome : null,
        );

        return response()->json([
            'ok' => true,
            'id_captacao_rota' => $config->id_captacao_rota,
            'nome_motorista' => $config->nome_motorista,
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
        $config = $this->matrizRotas->atualizarVeiculoRota(
            $lote,
            $rota,
            $veiculoId !== null ? (int) $veiculoId : null,
        );

        $veiculo = $config->veiculo;

        return response()->json([
            'ok' => true,
            'id_captacao_rota' => $config->id_captacao_rota,
            'id_veiculo' => $config->id_veiculo,
            'veiculo_rotulo' => $veiculo !== null
                ? "{$veiculo->nome} (SBS {$veiculo->id_sbs})"
                : null,
        ]);
    }

    public function concluirRota(Request $request, CaptacaoLote $lote, CaptacaoRota $rota): JsonResponse
    {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403);
        }

        $this->matrizRotas->concluirRota($lote, $rota);

        return $this->respostaMatrizRotas($lote);
    }

    public function reabrirRota(Request $request, CaptacaoLote $lote, CaptacaoRota $rota): JsonResponse
    {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403);
        }

        $this->matrizRotas->reabrirRota($lote, $rota);

        return $this->respostaMatrizRotas($lote);
    }

    public function downloadRomaneioRota(Request $request, CaptacaoLote $lote, CaptacaoRota $rota): Response
    {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403);
        }

        $pdfService = app(RomaneioRotaPdfService::class);
        $dados = $pdfService->dadosParaPdf($lote, $rota);
        $pdf = $pdfService->gerarPdf($lote, $rota);

        return $pdf->download($dados['nome_arquivo']);
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

        return $this->respostaMatrizAposAlteracaoLoja($lote);
    }

    public function updateSaidaFisicaVenda(
        UpdateCaptacaoSaidaFisicaVendaRequest $request,
        CaptacaoLote $lote,
        Cliente $cliente,
    ): JsonResponse {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403);
        }

        $idUnidade = (int) $request->validated('id_unidade_negocio_saida_venda');

        $pedido = $lote->status === CaptacaoLoteStatus::CaptacaoEmAndamento
            ? $this->pedidos->atualizarSaidaFisicaVendaPedidoPorLoja(
                $lote,
                $cliente->id,
                $idUnidade,
                PedidoOrigem::Web,
                $request->user(),
            )
            : $this->pedidos->atualizarSaidaFisicaVenda(
                $lote,
                $cliente->id,
                $idUnidade,
                PedidoOrigem::Web,
                $request->user(),
            );

        return response()->json([
            'ok' => true,
            'id_cliente' => $cliente->id,
            'id_unidade_negocio_saida_venda' => $pedido->id_unidade_negocio_saida_venda,
        ]);
    }

    public function removerLoja(RemoverLojaMatrizRequest $request, CaptacaoLote $lote): JsonResponse
    {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403);
        }

        $this->pedidos->removerLojaDaMatriz(
            $lote,
            (int) $request->validated('id_cliente'),
            PedidoOrigem::Web,
            $request->user(),
        );

        return $this->respostaMatrizAposAlteracaoLoja($lote);
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
            'removido' => $item === null,
            'item' => $item === null ? null : [
                'id' => $item->id,
                'version' => $item->version,
                'quantidade' => $item->quantidade,
                'preco_venda' => $item->preco_venda,
                'custo_referencia' => $item->custo_referencia,
            ],
        ]);
    }

    private function respostaMatrizAposAlteracaoLoja(CaptacaoLote $lote): JsonResponse
    {
        $lote->refresh()->load(['pedidos.itens', 'pedidos.cliente']);
        $matriz = $this->vinculos->dadosMatriz($lote);

        return response()->json(array_merge(
            $this->matrizEstado->snapshot($lote),
            [
                'ok' => true,
                'clientes_disponiveis' => $this->clientesParaSelect($matriz['clientesDisponiveis']),
                'clientes_na_matriz' => $this->clientesParaSelect($matriz['clientes']),
            ],
        ));
    }

    private function respostaMatrizRotas(CaptacaoLote $lote): JsonResponse
    {
        return response()->json(array_merge(
            $this->matrizEstado->snapshot($lote->refresh()),
            ['ok' => true],
        ));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Cliente>  $clientes
     * @return list<array{id: int, nome: string}>
     */
    private function clientesParaSelect(\Illuminate\Support\Collection $clientes): array
    {
        return $clientes
            ->map(fn (Cliente $c) => [
                'id' => $c->id,
                'nome' => $c->fantasia ?: $c->razao_social,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{podeConcluirCaptacaoLote: bool, pendenciasConclusaoCaptacaoLote: list<string>}
     */
    private function dadosConclusaoCaptacaoLote(CaptacaoLote $lote): array
    {
        $validacao = $this->concluirCaptacaoLote->pendenciasParaConcluir($lote);

        return [
            'podeConcluirCaptacaoLote' => $validacao['pode'],
            'pendenciasConclusaoCaptacaoLote' => $validacao['pendencias'],
        ];
    }
}
