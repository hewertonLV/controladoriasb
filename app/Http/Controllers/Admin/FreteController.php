<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FreteStatusSituacao;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFreteRequest;
use App\Http\Requests\Admin\UpdateFreteRequest;
use App\Models\Frete;
use App\Models\FreteHistorico;
use App\Models\Veiculo;
use App\Queries\FreteQuery;
use App\Services\Fretes\FreteAuditoriaService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FreteController extends Controller
{
    public function __construct(
        private readonly FreteAuditoriaService $auditoria,
        private readonly FreteQuery $freteQuery,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->freteQuery->filtrosFromRequest($request);
        $query = $this->freteQuery->aplicarFiltros(Frete::query(), $filtros);

        if ($filtros['per_page'] === 'all') {
            $total = (clone $query)->toBase()->count();
            $resultados = $query->get();
            $fretes = $resultados;
            $exibindo = $resultados->count();
        } else {
            $paginator = $query->paginate((int) $filtros['per_page'])->appends($filtros);
            $fretes = $paginator;
            $total = $paginator->total();
            $exibindo = count((array) $paginator->items());
        }

        $payload = [
            'fretes' => $fretes,
            'filtros' => $filtros,
            'perPageOptions' => FreteQuery::PER_PAGE_OPTIONS,
            'total' => $total,
            'exibindo' => $exibindo,
        ];

        if ($request->ajax()) {
            return view('admin.fretes._table', $payload);
        }

        return view('admin.fretes.index', $payload);
    }

    public function create(): View
    {
        return view('admin.fretes.create', [
            'frete' => new Frete([
                'status_situacao' => FreteStatusSituacao::ABERTA->value,
                'valor' => '0.00',
                'valor_fruta_kg' => '0.00',
            ]),
            'veiculos' => $this->veiculosAtivos(),
        ]);
    }

    public function store(StoreFreteRequest $request): RedirectResponse
    {
        $user = $request->user();
        $dados = $request->validated();

        $frete = DB::transaction(function () use ($dados, $user) {
            $frete = Frete::create($dados);

            $this->auditoria->registrarCriacao(
                $frete,
                $user,
                FreteHistorico::ORIGEM_MANUAL,
            );

            return $frete;
        });

        return redirect()
            ->route('admin.fretes.index')
            ->with('success', "Frete \"{$frete->nome}\" cadastrado com sucesso.");
    }

    public function edit(Frete $frete): View
    {
        return view('admin.fretes.edit', [
            'frete' => $frete,
            'veiculos' => $this->veiculosAtivos(),
        ]);
    }

    public function update(UpdateFreteRequest $request, Frete $frete): RedirectResponse
    {
        $user = $request->user();
        $dados = $request->validated();

        DB::transaction(function () use ($frete, $dados, $user) {
            $antes = $this->auditoria->snapshot($frete);

            $frete->update($dados);

            $depois = $this->auditoria->snapshot($frete->fresh());

            $this->auditoria->registrarAtualizacao(
                $frete,
                $antes,
                $depois,
                $user,
                FreteHistorico::ORIGEM_MANUAL,
            );
        });

        return redirect()
            ->route('admin.fretes.index')
            ->with('success', "Frete \"{$frete->nome}\" atualizado com sucesso.");
    }

    public function historico(Frete $frete): View
    {
        $historicos = $frete->historicos()
            ->with('user')
            ->paginate(50);

        return view('admin.fretes.historico', [
            'frete' => $frete->load('veiculo'),
            'historicos' => $historicos,
        ]);
    }

    /**
     * @return Collection<int, Veiculo>
     */
    private function veiculosAtivos()
    {
        return Veiculo::query()
            ->ativos()
            ->orderBy('nome')
            ->get(['id', 'id_sbs', 'nome']);
    }
}
