<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Captacao\StoreCaptacaoLoteRequest;
use App\Models\Captacao\CaptacaoLote;
use App\Models\UnidadeNegocio;
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
        private readonly RomaneioCarregamentoService $romaneioCarregamento,
        private readonly RomaneioAbastecimentoService $romaneioAbastecimento,
        private readonly UnidadeNegocioAccessService $unidadeAccess,
    ) {}

    public function index(Request $request): View
    {
        $query = CaptacaoLote::query()
            ->with(['unidadeFaturamento:id,nome', 'unidadeGalpao:id,nome'])
            ->orderByDesc('data_referencia');

        if ($request->filled('data_referencia')) {
            $query->whereDate('data_referencia', $request->string('data_referencia'));
        }

        $lotes = $query->paginate(20)->withQueryString();

        return view('admin.captacao.lotes.index', [
            'lotes' => $lotes,
            'filtros' => $request->only(['data_referencia']),
            'faturamentos' => UnidadeNegocio::query()
                ->where('emite_nota_fiscal', true)
                ->where('is_hub', false)
                ->orderBy('nome')
                ->get(['id', 'nome']),
            'galpoes' => UnidadeNegocio::query()
                ->where('is_galpao_operacional', true)
                ->orderBy('nome')
                ->get(['id', 'nome']),
        ]);
    }

    public function store(StoreCaptacaoLoteRequest $request): RedirectResponse
    {
        $dados = $request->validated();

        if (! $this->unidadeAccess->canAccess($request->user(), (int) $dados['id_unidade_negocio_galpao'])) {
            abort(403, UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
        }

        $lote = $this->lotes->abrirOuRecuperarLote(
            $dados['data_referencia'],
            (int) $dados['id_unidade_negocio_faturamento'],
            (int) $dados['id_unidade_negocio_galpao'],
        );

        return redirect()
            ->route('admin.captacao.lotes.show', $lote)
            ->with('success', 'Lote de captação aberto com sucesso.');
    }

    public function show(CaptacaoLote $lote): View
    {
        $lote->load(['unidadeFaturamento', 'unidadeGalpao']);

        $romaneioCarregamento = $this->romaneioCarregamento->preview($lote);

        return view('admin.captacao.lotes.show', [
            'lote' => $lote,
            'romaneioCarregamento' => $romaneioCarregamento,
            'romaneioCarregamentoTotaisGerais' => $this->romaneioCarregamento->totaisGerais($romaneioCarregamento),
            'romaneioAbastecimento' => $this->romaneioAbastecimento->preview($lote),
        ]);
    }
}
