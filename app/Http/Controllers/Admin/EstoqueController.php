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
use Illuminate\View\View;

class EstoqueController extends Controller
{
    public function __construct(
        private readonly EstoqueQuery $estoqueQuery,
        private readonly EstoqueMovimentacaoService $movimentacaoService,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->estoqueQuery->filtrosFromRequest($request);
        $query = $this->estoqueQuery->aplicarFiltros(
            Estoque::query()
                ->select('estoques.*')
                ->join('unidades_negocio', 'unidades_negocio.id', '=', 'estoques.id_unidade_negocio')
                ->join('frutas', 'frutas.id', '=', 'estoques.id_fruta')
                ->with([
                    'unidadeNegocio:id,nome,id_cigam',
                    'fruta:id,nome,id_cigam,unidade_medicao,kg_por_unidade_medicao',
                ]),
            $filtros,
        );

        if ($filtros['per_page'] === 'all') {
            $total = (clone $query)->toBase()->count();
            $resultados = $query->get();
            $estoques = $resultados;
            $exibindo = $resultados->count();
        } else {
            $paginator = $query->paginate((int) $filtros['per_page'])->appends($filtros);
            $estoques = $paginator;
            $total = $paginator->total();
            $exibindo = count((array) $paginator->items());
        }

        $unidadesFiltro = UnidadeNegocio::query()
            ->where('possui_estoque', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'id_cigam']);

        $frutasFiltro = Fruta::query()
            ->whereRaw('CAST(kg_por_unidade_medicao AS DECIMAL(15,2)) > 0')
            ->orderBy('nome')
            ->get(['id', 'nome', 'id_cigam']);

        $payload = [
            'estoques' => $estoques,
            'filtros' => $filtros,
            'perPageOptions' => EstoqueQuery::PER_PAGE_OPTIONS,
            'total' => $total,
            'exibindo' => $exibindo,
            'unidadesFiltro' => $unidadesFiltro,
            'frutasFiltro' => $frutasFiltro,
        ];

        if ($request->ajax()) {
            return view('admin.estoques._table', $payload);
        }

        return view('admin.estoques.index', $payload);
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
}
