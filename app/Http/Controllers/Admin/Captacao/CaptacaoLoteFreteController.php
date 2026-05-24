<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Enums\FreteStatusSituacao;
use App\Http\Controllers\Controller;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteFreteLinha;
use App\Models\Captacao\CaptacaoLoteMovimentacao;
use App\Models\Frete;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use App\Services\Movimentacoes\TransferenciaMovimentacaoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CaptacaoLoteFreteController extends Controller
{
    public function index(Request $request, CaptacaoLote $lote): View
    {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403, UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
        }

        $vinculos = CaptacaoLoteMovimentacao::query()
            ->where('id_captacao_lote', $lote->id)
            ->where('tipo', CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA)
            ->whereNotNull('transferencia_origem_id')
            ->with('fruta:id,nome')
            ->get();

        $transferencias = $vinculos->map(function (CaptacaoLoteMovimentacao $vinculo) {
            $saida = Movimentacao::query()
                ->where('transferencia_origem_id', $vinculo->transferencia_origem_id)
                ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
                ->orderByDesc('id')
                ->first();

            return [
                'vinculo' => $vinculo,
                'saida' => $saida,
                'id_frete_atual' => $saida?->id_frete,
            ];
        });

        $fretesAbertos = Frete::query()
            ->where('status_situacao', FreteStatusSituacao::ABERTA->value)
            ->orderBy('nome')
            ->get(['id', 'nome', 'valor']);

        $fretePorFruta = CaptacaoLoteFreteLinha::query()
            ->where('id_captacao_lote', $lote->id)
            ->pluck('id_frete', 'id_fruta');

        return view('admin.captacao.fretes.index', [
            'lote' => $lote,
            'transferencias' => $transferencias,
            'fretesAbertos' => $fretesAbertos,
            'fretePorFruta' => $fretePorFruta,
        ]);
    }

    public function vincularTransferencia(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        $dados = $request->validate([
            'transferencia_origem_id' => ['required', 'integer'],
            'id_frete' => ['nullable', 'integer', 'exists:fretes,id'],
        ]);

        app(TransferenciaMovimentacaoService::class)->vincularFrete(
            (int) $dados['transferencia_origem_id'],
            ['id_frete' => $dados['id_frete'] ?? null],
        );

        return back()->with('success', 'Frete da transferência atualizado.');
    }

    public function vincularFrutaVenda(Request $request, CaptacaoLote $lote): RedirectResponse
    {
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

        return back()->with('success', 'Frete vinculado à fruta para faturamento.');
    }
}
