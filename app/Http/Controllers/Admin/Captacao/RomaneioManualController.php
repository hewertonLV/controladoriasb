<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Actions\Captacao\ConfirmarRomaneioManualAction;
use App\Actions\Captacao\ConcluirTransferenciaRomaneioManualAction;
use App\Actions\Captacao\IniciarTransferenciaCiganAction;
use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Captacao\AdicionarRomaneioManualFrutaRequest;
use App\Http\Requests\Admin\Captacao\StoreRomaneioManualRequest;
use App\Http\Requests\Admin\Captacao\UpdateRomaneioManualLinhaRequest;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoRomaneioManualLinha;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Services\Captacao\CaptacaoLoteService;
use App\Services\Captacao\RomaneioAbastecimentoService;
use App\Services\Captacao\RomaneioManualService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use App\Support\Captacao\CaptacaoLotePipelineUi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RomaneioManualController extends Controller
{
    public function __construct(
        private readonly CaptacaoLoteService $lotes,
        private readonly RomaneioAbastecimentoService $romaneioAbastecimento,
        private readonly RomaneioManualService $romaneioManual,
    ) {}

    public function create(): View
    {
        return view('admin.captacao.romaneio-manual.create', [
            'faturamentos' => $this->faturamentos(),
            'galpoes' => $this->galpoes(),
        ]);
    }

    public function store(StoreRomaneioManualRequest $request): RedirectResponse
    {
        $dados = $request->validated();

        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), (int) $dados['id_unidade_negocio_galpao'])) {
            abort(403, UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
        }

        $lote = $this->lotes->criarRomaneioManual(
            $dados['data_referencia'],
            (int) $dados['id_unidade_negocio_faturamento'],
            (int) $dados['id_unidade_negocio_galpao'],
        );

        return redirect()
            ->route('admin.captacao.romaneio-manual.edit', $lote)
            ->with('success', 'Romaneio manual aberto. Adicione as frutas e as quantidades em caixas.');
    }

    public function edit(Request $request, CaptacaoLote $lote): View|RedirectResponse
    {
        abort_unless($lote->tipo === CaptacaoLoteTipo::RomaneioManual, 404);

        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403, UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
        }

        if ($lote->status !== CaptacaoLoteStatus::CaptacaoEmAndamento) {
            return redirect()->route('admin.captacao.romaneio-manual.show', $lote);
        }

        return $this->viewWorkspace($lote, editavel: true);
    }

    public function show(Request $request, CaptacaoLote $lote): View|RedirectResponse
    {
        abort_unless($lote->tipo === CaptacaoLoteTipo::RomaneioManual, 404);

        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403, UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
        }

        if ($lote->status === CaptacaoLoteStatus::CaptacaoEmAndamento) {
            return redirect()->route('admin.captacao.romaneio-manual.edit', $lote);
        }

        return $this->viewWorkspace($lote, editavel: false);
    }

    public function adicionarFruta(AdicionarRomaneioManualFrutaRequest $request, CaptacaoLote $lote): JsonResponse
    {
        $this->assertGalpao($request, $lote);

        $linha = $this->romaneioManual->adicionarFruta(
            $lote,
            (int) $request->validated('id_fruta'),
            (int) $request->validated('id_unidade_origem_fisica'),
            $request->validated('motivo'),
        );

        $linha->load(['fruta:id,nome', 'unidadeOrigemFisica:id,nome']);

        return response()->json([
            'ok' => true,
            'linha' => [
                'id' => $linha->id,
                'id_fruta' => $linha->id_fruta,
                'fruta_nome' => $linha->fruta->nome,
                'quantidade' => (float) $linha->quantidade,
                'id_unidade_origem_fisica' => $linha->id_unidade_origem_fisica,
                'origem_nome' => $linha->unidadeOrigemFisica->nome,
                'motivo' => $linha->motivo,
            ],
        ]);
    }

    public function updateLinha(
        UpdateRomaneioManualLinhaRequest $request,
        CaptacaoLote $lote,
        CaptacaoRomaneioManualLinha $linha,
    ): JsonResponse {
        $this->assertGalpao($request, $lote);

        $atualizada = $this->romaneioManual->atualizarLinha($lote, $linha, $request->validated());

        return response()->json([
            'ok' => true,
            'linha' => [
                'id' => $atualizada->id,
                'quantidade' => (float) $atualizada->quantidade,
            ],
        ]);
    }

    public function confirmar(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        $this->assertGalpao($request, $lote);
        app(ConfirmarRomaneioManualAction::class)->executar($lote);

        return redirect()
            ->route('admin.captacao.romaneio-manual.show', $lote)
            ->with('success', 'Romaneio fechado. Você pode iniciar a transferência.');
    }

    public function iniciarTransferencia(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        $this->assertGalpao($request, $lote);
        app(IniciarTransferenciaCiganAction::class)->executar($lote, $request->user());

        return redirect()
            ->route('admin.captacao.romaneio-manual.show', $lote)
            ->with('success', 'Transferência Cigan iniciada.');
    }

    public function concluirTransferencia(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        $this->assertGalpao($request, $lote);
        app(ConcluirTransferenciaRomaneioManualAction::class)->executar($lote);

        return redirect()
            ->route('admin.captacao.romaneio-manual.show', $lote)
            ->with('success', 'Transferência concluída no SB.');
    }

    private function viewWorkspace(CaptacaoLote $lote, bool $editavel): View
    {
        $lote->load(['unidadeFaturamento', 'unidadeGalpao', 'manualLinhas.fruta', 'manualLinhas.unidadeOrigemFisica']);

        return view('admin.captacao.romaneio-manual.edit', [
            'lote' => $lote,
            'editavel' => $editavel,
            'romaneioAbastecimento' => $this->romaneioAbastecimento->preview($lote),
            'proximaAcao' => CaptacaoLotePipelineUi::proximaAcao($lote),
            'hubs' => UnidadeNegocio::query()->where('is_hub', true)->orderBy('nome')->get(['id', 'nome']),
            'frutas' => Fruta::query()->orderBy('nome')->get(['id', 'nome']),
        ]);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, UnidadeNegocio> */
    private function faturamentos()
    {
        return UnidadeNegocio::query()
            ->where('emite_nota_fiscal', true)
            ->where('is_hub', false)
            ->orderBy('nome')
            ->get(['id', 'nome']);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, UnidadeNegocio> */
    private function galpoes()
    {
        return UnidadeNegocio::query()
            ->where('is_galpao_operacional', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);
    }

    private function assertGalpao(Request $request, CaptacaoLote $lote): void
    {
        abort_unless($lote->tipo === CaptacaoLoteTipo::RomaneioManual, 404);

        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403, UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
        }
    }
}
