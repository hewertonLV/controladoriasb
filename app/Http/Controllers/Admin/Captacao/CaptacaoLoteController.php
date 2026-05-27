<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Actions\Captacao\ExcluirCaptacaoLoteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Captacao\StoreCaptacaoLoteRequest;
use App\Enums\CaptacaoLoteStatus;
use App\Enums\CaptacaoLoteTipo;
use App\Models\Captacao\CaptacaoCarteira;
use App\Models\Captacao\CaptacaoLote;
use App\Services\Captacao\CaptacaoLoteService;
use App\Services\Captacao\RomaneioAbastecimentoService;
use App\Services\Captacao\RomaneioCarregamentoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CaptacaoLoteController extends Controller
{
    public function __construct(
        private readonly CaptacaoLoteService $lotes,
        private readonly ExcluirCaptacaoLoteAction $excluirLote,
        private readonly RomaneioCarregamentoService $romaneioCarregamento,
        private readonly RomaneioAbastecimentoService $romaneioAbastecimento,
        private readonly UnidadeNegocioAccessService $unidadeAccess,
    ) {}

    public function index(Request $request): View
    {
        $query = CaptacaoLote::query()
            ->with(['carteira:id,nome', 'unidadeFaturamento:id,nome', 'unidadeGalpao:id,nome'])
            ->orderByDesc('data_referencia');

        $lotes = $query->paginate(20);

        return view('admin.captacao.lotes.index', [
            'lotes' => $lotes,
            'carteiras' => CaptacaoCarteira::query()
                ->where('ativo', true)
                ->orderBy('nome')
                ->get(['id', 'nome', 'id_unidade_negocio_faturamento', 'id_unidade_negocio_galpao']),
        ]);
    }

    public function store(StoreCaptacaoLoteRequest $request): RedirectResponse
    {
        $dados = $request->validated();
        $carteira = CaptacaoCarteira::query()->findOrFail((int) $dados['id_captacao_carteira']);

        if (! $this->unidadeAccess->canAccess($request->user(), (int) $carteira->id_unidade_negocio_galpao)) {
            abort(403, UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
        }

        $jaEmAndamento = $this->lotes->possuiCaptacaoEmAndamento(
            $dados['data_referencia'],
            (int) $carteira->id,
            CaptacaoLoteTipo::CaptacaoPedidos,
        );

        $complementar = ! $jaEmAndamento && $this->lotes->possuiOutroLoteNaCarteiraData(
            $dados['data_referencia'],
            (int) $carteira->id,
            CaptacaoLoteTipo::CaptacaoPedidos,
        );

        $lote = $this->lotes->abrirOuRecuperarLotePorCarteira(
            $dados['data_referencia'],
            (int) $carteira->id,
        );

        $mensagem = match (true) {
            $jaEmAndamento => 'Já existe captação em andamento para esta carteira e data — lote recuperado.',
            $complementar => 'Captação complementar aberta com sucesso.',
            default => 'Nova captação aberta com sucesso.',
        };

        return redirect()
            ->route('admin.captacao.lotes.show', $lote)
            ->with('success', $mensagem);
    }

    public function show(CaptacaoLote $lote): View
    {
        $lote = $this->lotes->sincronizarStatusComFaturamentoFinalizado($lote);
        $lote->load(['carteira', 'unidadeFaturamento', 'unidadeGalpao']);

        $romaneioCarregamento = $this->romaneioCarregamento->preview($lote);

        return view('admin.captacao.lotes.show', [
            'lote' => $lote,
            'romaneioCarregamento' => $romaneioCarregamento,
            'romaneioCarregamentoTotaisGerais' => $this->romaneioCarregamento->totaisGerais($romaneioCarregamento),
            'romaneioAbastecimento' => $this->romaneioAbastecimento->preview($lote),
        ]);
    }

    public function destroy(Request $request, CaptacaoLote $lote): RedirectResponse
    {
        if (! $this->unidadeAccess->canAccess($request->user(), (int) $lote->id_unidade_negocio_galpao)) {
            abort(403, UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
        }

        if ($lote->status !== CaptacaoLoteStatus::CaptacaoEmAndamento) {
            return redirect()
                ->route('admin.captacao.lotes.index')
                ->with('error', 'Só é possível excluir captações em andamento.');
        }

        $this->excluirLote->executar($lote);

        return redirect()
            ->route('admin.captacao.lotes.index')
            ->with('success', 'Captação excluída com sucesso.');
    }
}
