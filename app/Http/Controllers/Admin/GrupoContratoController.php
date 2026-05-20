<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGrupoContratoDescontoRequest;
use App\Http\Requests\Admin\StoreGrupoContratoMembroRequest;
use App\Http\Requests\Admin\StoreGrupoContratoRequest;
use App\Http\Requests\Admin\UpdateGrupoContratoRequest;
use App\Models\Cliente;
use App\Models\GrupoContrato;
use App\Queries\GrupoContratoQuery;
use App\Services\GruposContrato\GrupoContratoAuditoriaService;
use App\Services\GruposContrato\GrupoContratoDescontoService;
use App\Services\GruposContrato\GrupoContratoMembroService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GrupoContratoController extends Controller
{
    public function __construct(
        private readonly GrupoContratoQuery $grupoContratoQuery,
        private readonly GrupoContratoAuditoriaService $auditoria,
        private readonly GrupoContratoMembroService $membroService,
        private readonly GrupoContratoDescontoService $descontoService,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->grupoContratoQuery->filtrosFromRequest($request);
        $gruposContrato = $this->grupoContratoQuery->aplicarFiltros(
            GrupoContrato::query()
                ->withCount('membros')
                ->with(['descontos' => fn ($q) => $q->latest('competencia')->limit(1)]),
            $filtros,
        )->get();

        return view('admin.grupos-contrato.index', [
            'gruposContrato' => $gruposContrato,
            'filtros' => $filtros,
        ]);
    }

    public function create(): View
    {
        return view('admin.grupos-contrato.create', [
            'grupoContrato' => new GrupoContrato(['ativo' => true]),
        ]);
    }

    public function store(StoreGrupoContratoRequest $request): RedirectResponse
    {
        $grupo = DB::transaction(function () use ($request) {
            $dados = $request->validated();
            $dados['created_by'] = $request->user()?->id;
            $dados['updated_by'] = $request->user()?->id;

            $grupo = GrupoContrato::create($dados);
            $this->auditoria->registrarCriacaoGrupo($grupo, $request->user());

            return $grupo;
        });

        return redirect()
            ->route('admin.grupos-contrato.index')
            ->with('success', "Grupo de contrato \"{$grupo->nome}\" cadastrado com sucesso.");
    }

    public function show(GrupoContrato $grupoContrato): View
    {
        return view('admin.grupos-contrato.show', [
            'grupoContrato' => $grupoContrato->load(['membros.cliente', 'descontos' => fn ($q) => $q->orderByDesc('competencia')]),
            'clientes' => Cliente::query()->orderBy('razao_social')->get(['id', 'razao_social', 'fantasia', 'id_cigam']),
        ]);
    }

    public function edit(GrupoContrato $grupoContrato): View
    {
        return view('admin.grupos-contrato.edit', [
            'grupoContrato' => $grupoContrato,
        ]);
    }

    public function update(UpdateGrupoContratoRequest $request, GrupoContrato $grupoContrato): RedirectResponse
    {
        DB::transaction(function () use ($request, $grupoContrato) {
            $antes = $this->auditoria->snapshotGrupo($grupoContrato);
            $dados = $request->validated();
            $dados['updated_by'] = $request->user()?->id;

            $grupoContrato->update($dados);
            $this->auditoria->registrarAtualizacaoGrupo($grupoContrato->fresh(), $antes, $request->user());
        });

        return redirect()
            ->route('admin.grupos-contrato.index')
            ->with('success', "Grupo de contrato \"{$grupoContrato->nome}\" atualizado com sucesso.");
    }

    public function storeMembro(StoreGrupoContratoMembroRequest $request, GrupoContrato $grupoContrato): RedirectResponse
    {
        $this->membroService->adicionar($grupoContrato, $request->validated(), $request->user());

        return redirect()
            ->route('admin.grupos-contrato.show', $grupoContrato)
            ->with('success', 'Cliente vinculado ao grupo de contrato.');
    }

    public function storeDesconto(StoreGrupoContratoDescontoRequest $request, GrupoContrato $grupoContrato): RedirectResponse
    {
        $this->descontoService->lancar($grupoContrato, $request->validated(), $request->user());

        return redirect()
            ->route('admin.grupos-contrato.show', $grupoContrato)
            ->with('success', 'Desconto mensal lançado.');
    }

    public function historico(GrupoContrato $grupoContrato): View
    {
        return view('admin.grupos-contrato.historico', [
            'grupoContrato' => $grupoContrato,
            'historicos' => $grupoContrato->historicos()->with('user')->latest('created_at')->paginate(50),
        ]);
    }
}
