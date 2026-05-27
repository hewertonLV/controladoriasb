<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Actions\Captacao\FinalizarCaptacaoFaturamentoAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Captacao\FinalizarCaptacaoFaturamentoRequest;
use App\Models\Captacao\CaptacaoLote;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\RedirectResponse;

class CaptacaoFaturamentoController extends Controller
{
    public function __construct(
        private readonly FinalizarCaptacaoFaturamentoAction $finalizar,
        private readonly UnidadeNegocioAccessService $unidadeAccess,
    ) {}

    public function finalizar(FinalizarCaptacaoFaturamentoRequest $request): RedirectResponse
    {
        $idFaturamento = (int) $request->validated('id_unidade_negocio_faturamento');
        $loteContexto = null;

        if ($request->filled('id_captacao_lote')) {
            $loteContexto = CaptacaoLote::query()->findOrFail((int) $request->validated('id_captacao_lote'));
        }

        if (! $this->podeFinalizarCaptacao($request->user(), $idFaturamento, $loteContexto)) {
            return back()
                ->withInput()
                ->with('error', UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
        }

        $this->finalizar->executar(
            $request->string('data_referencia')->toString(),
            $idFaturamento,
            $request->user(),
        );

        $mensagem = 'Captação do faturamento finalizada. Pronto para iniciar a transferência.';

        if ($loteContexto !== null) {
            return redirect()
                ->route('admin.captacao.lotes.show', $loteContexto->fresh())
                ->with('success', $mensagem);
        }

        return back()->with('success', $mensagem);
    }

    private function podeFinalizarCaptacao($user, int $idFaturamento, ?CaptacaoLote $loteContexto): bool
    {
        if ($this->unidadeAccess->canAccess($user, $idFaturamento)) {
            return true;
        }

        if ($loteContexto !== null && $this->unidadeAccess->canAccess($user, $loteContexto->id_unidade_negocio_galpao)) {
            return true;
        }

        return false;
    }
}
