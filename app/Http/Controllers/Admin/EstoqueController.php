<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMovimentacaoEstoqueRequest;
use App\Models\Estoque;
use App\Models\Fruta;
use App\Models\UnidadeNegocio;
use App\Queries\EstoqueQuery;
use App\Services\Estoques\EstoqueMovimentacaoService;
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
        $payload = [
            'unidadesCards' => $this->unidadesCards(),
        ];

        return view('admin.estoques.index', $payload);
    }

    public function unidade(UnidadeNegocio $unidadeNegocio): View
    {
        abort_unless($unidadeNegocio->possui_estoque, 404);

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
        $unidadeId = $request->query('id_unidade_negocio');
        $frutaId = $request->query('id_fruta');

        return view('admin.estoques.movimentar', [
            'unidades' => UnidadeNegocio::query()
                ->where('possui_estoque', true)
                ->orderBy('nome')
                ->get(['id', 'nome', 'id_cigam']),
            'frutas' => Fruta::query()
                ->whereRaw('CAST(kg_por_unidade_medicao AS DECIMAL(15,2)) > 0')
                ->orderBy('nome')
                ->get(['id', 'nome', 'id_cigam', 'unidade_medicao']),
            'idUnidadeSelecionada' => $unidadeId !== null && $unidadeId !== '' ? (int) $unidadeId : null,
            'idFrutaSelecionada' => $frutaId !== null && $frutaId !== '' ? (int) $frutaId : null,
        ]);
    }

    public function movimentarStore(StoreMovimentacaoEstoqueRequest $request): RedirectResponse
    {
        $dados = $request->validated();
        $unidade = UnidadeNegocio::query()->findOrFail((int) $dados['id_unidade_negocio']);
        $fruta = Fruta::query()->findOrFail((int) $dados['id_fruta']);

        try {
            $this->movimentacaoService->movimentarPorTipo(
                $unidade,
                $fruta,
                (string) $dados['tipo'],
                (string) $dados['quantidade_kg'],
                isset($dados['preco_medio_kg']) ? (string) $dados['preco_medio_kg'] : null,
            );
        } catch (\InvalidArgumentException $e) {
            return back()
                ->withInput()
                ->withErrors(['movimentacao' => $e->getMessage()]);
        }

        $estoque = Estoque::query()
            ->where('id_unidade_negocio', $unidade->id)
            ->where('id_fruta', $fruta->id)
            ->firstOrFail();

        return redirect()
            ->route('admin.estoques.show', $estoque)
            ->with('success', 'Movimentação registrada com sucesso.');
    }

    private function unidadesCards()
    {
        return UnidadeNegocio::query()
            ->leftJoin('estoques', 'estoques.id_unidade_negocio', '=', 'unidades_negocio.id')
            ->where('unidades_negocio.possui_estoque', true)
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
