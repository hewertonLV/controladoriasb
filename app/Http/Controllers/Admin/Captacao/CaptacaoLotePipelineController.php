<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Actions\Captacao\ConcluirEtapaFreteLoteAction;
use App\Actions\Captacao\FinalizarVendasLoteAction;
use App\Actions\Captacao\IniciarFaturamentoCiganAction;
use App\Actions\Captacao\IniciarTransferenciaCiganAction;
use App\Actions\Captacao\ValidarTransferenciasGerenciaisLoteAction;
use App\Http\Controllers\Controller;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteCiganExport;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CaptacaoLotePipelineController extends Controller
{
    public function iniciarTransferencia(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        $this->assertGalpao($request, $lote);
        app(IniciarTransferenciaCiganAction::class)->executar($lote, $request->user());

        return back()->with('success', 'Transferência Cigan iniciada.');
    }

    public function validarTransferencias(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        $this->assertGalpao($request, $lote);
        app(ValidarTransferenciasGerenciaisLoteAction::class)->executar($lote);

        return back()->with('success', 'Transferências gerenciais validadas.');
    }

    public function concluirFrete(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        $this->assertGalpao($request, $lote);
        app(ConcluirEtapaFreteLoteAction::class)->executar($lote);

        return back()->with('success', 'Etapa de frete concluída.');
    }

    public function iniciarFaturamento(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        $this->assertGalpao($request, $lote);
        app(IniciarFaturamentoCiganAction::class)->executar($lote, $request->user());

        return back()->with('success', 'Faturamento Cigan iniciado.');
    }

    public function finalizarVendas(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        $this->assertGalpao($request, $lote);
        app(FinalizarVendasLoteAction::class)->executar($lote, $request->user());

        return back()->with('success', 'Vendas finalizadas no SB.');
    }

    public function downloadCigan(CaptacaoLoteCiganExport $export): StreamedResponse
    {
        return Storage::disk('local')->download($export->caminho_arquivo);
    }

    private function assertGalpao(Request $request, CaptacaoLote $lote): void
    {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403, UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
        }
    }
}
