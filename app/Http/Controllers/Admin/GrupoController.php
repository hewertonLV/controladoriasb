<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGrupoRequest;
use App\Http\Requests\Admin\UpdateGrupoRequest;
use App\Models\Grupo;
use App\Models\GrupoHistorico;
use App\Queries\GrupoQuery;
use App\Services\Grupos\GrupoAuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GrupoController extends Controller
{
    public function __construct(
        private readonly GrupoAuditoriaService $auditoria,
        private readonly GrupoQuery $grupoQuery,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->grupoQuery->filtrosFromRequest($request);
        $query = $this->grupoQuery->aplicarFiltros(Grupo::query(), $filtros);

        if ($filtros['per_page'] === 'all') {
            $total = (clone $query)->toBase()->count();
            $resultados = $query->get();
            $grupos = $resultados;
            $exibindo = $resultados->count();
        } else {
            $paginator = $query->paginate((int) $filtros['per_page'])->appends($filtros);
            $grupos = $paginator;
            $total = $paginator->total();
            $exibindo = count((array) $paginator->items());
        }

        $payload = [
            'grupos' => $grupos,
            'filtros' => $filtros,
            'perPageOptions' => GrupoQuery::PER_PAGE_OPTIONS,
            'total' => $total,
            'exibindo' => $exibindo,
        ];

        if ($request->ajax()) {
            return view('admin.grupos._table', $payload);
        }

        return view('admin.grupos.index', $payload);
    }

    public function create(): View
    {
        return view('admin.grupos.create', [
            'grupo' => new Grupo,
        ]);
    }

    public function store(StoreGrupoRequest $request): RedirectResponse
    {
        $user = $request->user();
        $dados = $request->validated();

        $grupo = DB::transaction(function () use ($dados, $user) {
            $grupo = Grupo::create($dados);

            $this->auditoria->registrarCriacao(
                $grupo,
                $user,
                GrupoHistorico::ORIGEM_MANUAL,
            );

            return $grupo;
        });

        return redirect()
            ->route('admin.grupos.index')
            ->with('success', "Grupo \"{$grupo->nome}\" cadastrado com sucesso.");
    }

    public function edit(Grupo $grupo): View
    {
        return view('admin.grupos.edit', [
            'grupo' => $grupo,
        ]);
    }

    public function update(UpdateGrupoRequest $request, Grupo $grupo): RedirectResponse
    {
        $user = $request->user();
        $dados = $request->validated();

        DB::transaction(function () use ($grupo, $dados, $user) {
            $antes = $this->auditoria->snapshot($grupo);

            $grupo->update($dados);

            $depois = $this->auditoria->snapshot($grupo->fresh());

            $this->auditoria->registrarAtualizacao(
                $grupo,
                $antes,
                $depois,
                $user,
                GrupoHistorico::ORIGEM_MANUAL,
            );
        });

        return redirect()
            ->route('admin.grupos.index')
            ->with('success', "Grupo \"{$grupo->nome}\" atualizado com sucesso.");
    }

    public function historico(Grupo $grupo): View
    {
        $historicos = $grupo->historicos()
            ->with('user')
            ->paginate(50);

        return view('admin.grupos.historico', [
            'grupo' => $grupo,
            'historicos' => $historicos,
        ]);
    }
}
