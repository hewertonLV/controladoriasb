<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Http\Controllers\Controller;
use App\Models\Captacao\CaptacaoLoteMovimentacao;
use App\Services\Captacao\CaptacaoDemandasRotaExibicaoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VendaCaptacaoDemandaController extends Controller
{
    public function show(
        Request $request,
        CaptacaoLoteMovimentacao $demanda,
        CaptacaoDemandasRotaExibicaoService $exibicao,
    ): View {
        $this->assertDemandaVenda($demanda);
        $this->assertAcesso($request, $demanda);

        $card = $exibicao->cardDemandaCaptacao($demanda);
        abort_if($card === null, 404);

        return view('admin.movimentacoes.vendas.demanda-captacao.show', [
            'demanda' => $demanda,
            'card' => $card,
            'urlVoltar' => route('admin.movimentacoes.vendas.index'),
        ]);
    }

    private function assertDemandaVenda(CaptacaoLoteMovimentacao $demanda): void
    {
        if ($demanda->tipo !== CaptacaoLoteMovimentacao::TIPO_VENDA_NOTA) {
            abort(404);
        }
    }

    private function assertAcesso(Request $request, CaptacaoLoteMovimentacao $demanda): void
    {
        $demanda->loadMissing('lote');
        $galpaoId = (int) ($demanda->lote?->id_unidade_negocio_galpao ?? 0);

        if ($galpaoId <= 0 || ! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $galpaoId)) {
            abort(403);
        }
    }
}
