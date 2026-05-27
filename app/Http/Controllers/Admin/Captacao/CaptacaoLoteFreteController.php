<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Http\Controllers\Controller;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteFreteLinha;
use App\Services\Captacao\CaptacaoLoteFreteService;
use App\Services\Movimentacoes\TransferenciaMovimentacaoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CaptacaoLoteFreteController extends Controller
{
    public function __construct(
        private readonly CaptacaoLoteFreteService $freteService,
    ) {}

    public function index(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403, UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
        }

        return redirect()->route('admin.captacao.matriz.index', [
            'lote' => $lote->id,
            'aba' => $this->abaFretePadrao($lote),
        ]);
    }

    public function vincularTransferencia(Request $request, CaptacaoLote $lote): JsonResponse|RedirectResponse
    {
        $this->assertAcessoGalpao($request, $lote);
        $this->normalizarFreteOpcional($request);

        $dados = $request->validate([
            'transferencia_origem_id' => ['required', 'integer'],
            'id_frete' => ['nullable', 'integer', 'exists:fretes,id'],
        ]);

        app(TransferenciaMovimentacaoService::class)->vincularFrete(
            (int) $dados['transferencia_origem_id'],
            ['id_frete' => $dados['id_frete'] ?? null],
        );

        return $this->respostaVinculoFrete($request, $lote, 'frete-hub', $dados['id_frete'] ?? null, 'Frete da transferência atualizado.');
    }

    public function vincularFrutaVenda(Request $request, CaptacaoLote $lote): JsonResponse|RedirectResponse
    {
        $this->assertAcessoGalpao($request, $lote);
        $this->normalizarFreteOpcional($request);

        $dados = $request->validate([
            'id_fruta' => ['required', 'integer', 'exists:frutas,id'],
            'id_frete' => ['nullable', 'integer', 'exists:fretes,id'],
        ]);

        CaptacaoLoteFreteLinha::query()->updateOrCreate(
            [
                'id_captacao_lote' => $lote->id,
                'id_fruta' => $dados['id_fruta'],
            ],
            [
                'id_frete' => $dados['id_frete'] ?? null,
            ],
        );

        return $this->respostaVinculoFrete($request, $lote, 'frete-hub', $dados['id_frete'] ?? null, 'Frete vinculado à fruta para faturamento.');
    }

    public function vincularFreteVendaLoja(Request $request, CaptacaoLote $lote): JsonResponse|RedirectResponse
    {
        $this->assertAcessoGalpao($request, $lote);
        $this->normalizarFreteOpcional($request);

        $dados = $request->validate([
            'id_cliente' => ['required', 'integer', 'exists:clientes,id'],
            'id_frete' => ['nullable', 'integer', 'exists:fretes,id'],
        ]);

        $this->freteService->vincularFreteVendaLoja(
            $lote,
            (int) $dados['id_cliente'],
            isset($dados['id_frete']) ? (int) $dados['id_frete'] : null,
            $request->user(),
        );

        return $this->respostaVinculoFrete($request, $lote, 'frete-vendas', $dados['id_frete'] ?? null, 'Frete da venda atualizado.');
    }

    private function assertAcessoGalpao(Request $request, CaptacaoLote $lote): void
    {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403, UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
        }
    }

    private function normalizarFreteOpcional(Request $request): void
    {
        if (! $request->has('id_frete')) {
            return;
        }

        $valor = $request->input('id_frete');
        if ($valor === null || $valor === '') {
            $request->merge(['id_frete' => null]);
        }
    }

    /**
     * @return JsonResponse|RedirectResponse
     */
    private function respostaVinculoFrete(Request $request, CaptacaoLote $lote, string $aba, mixed $idFrete, string $mensagem): JsonResponse|RedirectResponse
    {
        $idFreteInt = $idFrete !== null && $idFrete !== '' ? (int) $idFrete : null;

        if ($request->wantsJson()) {
            return response()->json([
                'id_frete' => $idFreteInt,
                'status_label' => $idFreteInt ? 'Vinculado' : 'Sem Frete',
                'message' => $mensagem,
            ]);
        }

        return $this->redirectMatrizFrete($lote, $aba, $mensagem);
    }

    private function abaFretePadrao(CaptacaoLote $lote): string
    {
        $status = $lote->status;

        if ($status->exibeAbaFreteHub()) {
            return 'frete-hub';
        }

        if ($status->exibeAbaFreteVendas()) {
            return 'frete-vendas';
        }

        return 'arquivo-cigan';
    }

    private function redirectMatrizFrete(CaptacaoLote $lote, string $aba, string $mensagem): RedirectResponse
    {
        $status = $lote->fresh()->status;
        if (! in_array($aba, ['frete-hub', 'frete-vendas'], true)) {
            $aba = $this->abaFretePadrao($lote);
        }
        if ($aba === 'frete-hub' && ! $status->exibeAbaFreteHub()) {
            $aba = $status->exibeAbaFreteVendas() ? 'frete-vendas' : 'arquivo-cigan';
        }
        if ($aba === 'frete-vendas' && ! $status->exibeAbaFreteVendas()) {
            $aba = $status->exibeAbaFreteHub() ? 'frete-hub' : 'quantidade';
        }

        return redirect()
            ->route('admin.captacao.matriz.index', ['lote' => $lote->id, 'aba' => $aba])
            ->with('success', $mensagem);
    }
}
