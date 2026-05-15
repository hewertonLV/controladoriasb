<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePracaRequest;
use App\Http\Requests\Admin\UpdatePracaRequest;
use App\Models\Praca;
use App\Models\PracaHistorico;
use App\Models\UnidadeNegocio;
use App\Queries\PracaQuery;
use App\Services\Pracas\PracaAuditoriaService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PracaController extends Controller
{
    public function __construct(
        private readonly PracaAuditoriaService $auditoria,
        private readonly PracaQuery $pracaQuery,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->pracaQuery->filtrosFromRequest($request);
        $query = $this->pracaQuery->aplicarFiltros(
            Praca::query()->with('unidadeNegocio:id,nome,id_cigam'),
            $filtros,
        );

        if ($filtros['per_page'] === 'all') {
            $total = (clone $query)->toBase()->count();
            $resultados = $query->get();
            $pracas = $resultados;
            $exibindo = $resultados->count();
        } else {
            $paginator = $query->paginate((int) $filtros['per_page'])->appends($filtros);
            $pracas = $paginator;
            $total = $paginator->total();
            $exibindo = count((array) $paginator->items());
        }

        $payload = [
            'pracas' => $pracas,
            'filtros' => $filtros,
            'perPageOptions' => PracaQuery::PER_PAGE_OPTIONS,
            'total' => $total,
            'exibindo' => $exibindo,
        ];

        if ($request->ajax()) {
            return view('admin.pracas._table', $payload);
        }

        return view('admin.pracas.index', $payload);
    }

    public function create(): View
    {
        return view('admin.pracas.create', [
            'praca' => new Praca,
            'unidadesNegocio' => $this->unidadesParaSelect(),
        ]);
    }

    public function store(StorePracaRequest $request): RedirectResponse
    {
        $user = $request->user();
        $dados = $request->validated();

        $praca = DB::transaction(function () use ($dados, $user) {
            $praca = Praca::create($dados);

            $this->auditoria->registrarCriacao(
                $praca,
                $user,
                PracaHistorico::ORIGEM_MANUAL,
            );

            return $praca;
        });

        return redirect()
            ->route('admin.pracas.index')
            ->with('success', "Praça \"{$praca->nome}\" cadastrada com sucesso.");
    }

    public function edit(Praca $praca): View
    {
        return view('admin.pracas.edit', [
            'praca' => $praca,
            'unidadesNegocio' => $this->unidadesParaSelect(),
        ]);
    }

    public function update(UpdatePracaRequest $request, Praca $praca): RedirectResponse
    {
        $user = $request->user();
        $dados = $request->validated();

        DB::transaction(function () use ($praca, $dados, $user) {
            $antes = $this->auditoria->snapshot($praca);

            $praca->update($dados);

            $depois = $this->auditoria->snapshot($praca->fresh());

            $this->auditoria->registrarAtualizacao(
                $praca,
                $antes,
                $depois,
                $user,
                PracaHistorico::ORIGEM_MANUAL,
            );
        });

        return redirect()
            ->route('admin.pracas.index')
            ->with('success', "Praça \"{$praca->nome}\" atualizada com sucesso.");
    }

    public function historico(Praca $praca): View
    {
        $historicos = $praca->historicos()
            ->with('user')
            ->paginate(50);

        return view('admin.pracas.historico', [
            'praca' => $praca->load('unidadeNegocio:id,nome,id_cigam'),
            'historicos' => $historicos,
        ]);
    }

    /**
     * @return Collection<int, UnidadeNegocio>
     */
    private function unidadesParaSelect()
    {
        return UnidadeNegocio::query()
            ->orderBy('nome')
            ->get(['id', 'nome', 'id_cigam']);
    }
}
