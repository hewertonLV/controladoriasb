<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Http\Controllers\Controller;
use App\Models\Captacao\CaptacaoLoteMovimentacao;
use App\Services\Captacao\CaptacaoDemandaTransferenciaRotaService;
use App\Services\Captacao\CaptacaoDemandasRotaExibicaoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TransferenciaCaptacaoDemandaController extends Controller
{
    public function show(
        Request $request,
        CaptacaoLoteMovimentacao $demanda,
        CaptacaoDemandasRotaExibicaoService $exibicao,
    ): View {
        $this->assertDemandaTransferencia($demanda);
        $this->assertAcesso($request, $demanda);

        $card = $exibicao->cardDemandaCaptacao($demanda);
        abort_if($card === null, 404);

        return view('admin.movimentacoes.transferencias.demanda-captacao.show', [
            'demanda' => $demanda,
            'card' => $card,
            'lote' => $demanda->lote,
            'urlVoltar' => route('admin.movimentacoes.transferencias.index'),
        ]);
    }

    public function downloadCigam(
        Request $request,
        CaptacaoLoteMovimentacao $demanda,
        CaptacaoDemandaTransferenciaRotaService $transferencias,
    ): Response {
        $this->assertDemandaTransferencia($demanda);
        $this->assertAcesso($request, $demanda);

        $conteudo = $transferencias->gerarArquivoCigam($demanda);

        return response($conteudo, 200, [
            'Content-Type' => 'text/plain; charset=Windows-1252',
            'Content-Disposition' => 'attachment; filename="cigam-transferencia-demanda-'.$demanda->id.'.txt"',
        ]);
    }

    private function assertDemandaTransferencia(CaptacaoLoteMovimentacao $demanda): void
    {
        if ($demanda->tipo !== CaptacaoLoteMovimentacao::TIPO_TRANSFERENCIA) {
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
