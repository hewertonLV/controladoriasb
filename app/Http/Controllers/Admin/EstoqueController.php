<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMovimentacaoEstoqueRequest;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Queries\EstoqueQuery;
use App\Services\Estoques\EstoqueMovimentacaoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EstoqueController extends Controller
{
    public function __construct(
        private readonly EstoqueQuery $estoqueQuery,
        private readonly EstoqueMovimentacaoService $movimentacaoService,
    ) {}

    public function index(): View
    {
        return view('admin.estoques.index', [
            'unidades' => $this->unidadesParaListagem(),
        ]);
    }

    public function unidade(UnidadeNegocio $unidadeNegocio): View
    {
        abort_unless($unidadeNegocio->possui_estoque, 404);
        abort_unless(app(UnidadeNegocioAccessService::class)->canAccess(auth()->user(), $unidadeNegocio->id), 403);

        $filtros = $this->estoqueQuery->normalizarFiltros([
            'id_unidade_negocio' => $unidadeNegocio->id,
            'per_page' => 'all',
            'sort' => 'fruta',
            'direction' => 'asc',
        ]);

        $estoques = $this->estoqueQuery->aplicarFiltros(
            Estoque::query()
                ->select('estoques.*')
                ->join('unidades_negocio', 'unidades_negocio.id', '=', 'estoques.id_unidade_negocio')
                ->join('frutas', 'frutas.id', '=', 'estoques.id_fruta')
                ->with([
                    'unidadeNegocio:id,nome,id_cigam',
                    'fruta:id,nome,id_cigam,unidade_medicao,kg_por_unidade_medicao',
                ]),
            $filtros,
        )->get();

        return view('admin.estoques.unidade', [
            'estoques' => $estoques,
            'filtros' => $filtros,
            'total' => $estoques->count(),
            'exibindo' => $estoques->count(),
            'unidadeSelecionada' => $unidadeNegocio,
        ]);
    }

    public function show(Estoque $estoque): View
    {
        $estoque->load([
            'unidadeNegocio:id,nome,id_cigam',
            'fruta:id,nome,id_cigam,unidade_medicao,kg_por_unidade_medicao',
        ]);

        $movimentacoes = $estoque->movimentacoes()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(30);

        return view('admin.estoques.show', [
            'estoque' => $estoque,
            'movimentacoes' => $movimentacoes,
        ]);
    }

    public function movimentarForm(Request $request): View
    {
        $unidadeId = $request->integer('id_unidade_negocio');
        abort_if($unidadeId <= 0, 404);

        $unidade = UnidadeNegocio::query()
            ->where('possui_estoque', true)
            ->findOrFail($unidadeId);

        abort_unless(app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $unidade->id), 403);

        $frutaId = $request->query('id_fruta');
        $idFrutaPreselecionada = $frutaId !== null && $frutaId !== '' ? (int) $frutaId : null;

        return view('admin.estoques.movimentar', [
            'unidade' => $unidade,
            'frutas' => Fruta::query()
                ->whereRaw('CAST(kg_por_unidade_medicao AS DECIMAL(15,2)) > 0')
                ->orderBy('nome')
                ->get(['id', 'nome', 'id_cigam', 'unidade_medicao', 'kg_por_unidade_medicao']),
            'idFrutaPreselecionada' => $idFrutaPreselecionada,
        ]);
    }

    public function movimentarStore(StoreMovimentacaoEstoqueRequest $request): RedirectResponse
    {
        $dados = $request->validated();
        $unidade = UnidadeNegocio::query()->findOrFail((int) $dados['id_unidade_negocio']);
        $itens = $dados['itens'];
        $registrados = 0;

        try {
            DB::transaction(function () use ($unidade, $itens, &$registrados): void {
                foreach ($itens as $item) {
                    $fruta = Fruta::query()->findOrFail((int) $item['id_fruta']);
                    $this->movimentacaoService->movimentarEntradaPorUnidadeMedicao(
                        $unidade,
                        $fruta,
                        (string) $item['qtd_fruta_um'],
                        (string) $item['preco_fruta_um'],
                    );
                    $registrados++;
                }
            });
        } catch (\InvalidArgumentException $e) {
            return back()
                ->withInput()
                ->withErrors(['movimentacao' => $e->getMessage()]);
        }

        $mensagem = $registrados === 1
            ? 'Entrada de estoque registrada com sucesso.'
            : "{$registrados} entradas de estoque registradas com sucesso.";

        return redirect()
            ->route('admin.estoques.unidade', $unidade)
            ->with('success', $mensagem);
    }

    private function unidadesParaListagem()
    {
        return UnidadeNegocio::query()
            ->leftJoin('estoques', 'estoques.id_unidade_negocio', '=', 'unidades_negocio.id')
            ->where('unidades_negocio.possui_estoque', true)
            ->permitidasPara(auth()->user())
            ->groupBy('unidades_negocio.id', 'unidades_negocio.nome', 'unidades_negocio.id_cigam')
            ->orderBy('unidades_negocio.nome')
            ->get([
                'unidades_negocio.id',
                'unidades_negocio.nome',
                'unidades_negocio.id_cigam',
                DB::raw('COUNT(estoques.id) as posicoes_count'),
                DB::raw('COALESCE(SUM(estoques.qtd_fruta_kg), 0) as total_kg'),
                DB::raw('COALESCE(SUM(estoques.valor_total_acumulado), 0) as valor_total'),
            ]);
    }
}
