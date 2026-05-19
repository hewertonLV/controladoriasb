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
        $pracas = $this->pracaQuery->aplicarFiltros(
            Praca::query()->with('unidadeNegocio:id,nome,id_cigam'),
            $filtros,
        )->get();

        return view('admin.pracas.index', [
            'pracas' => $pracas,
            'filtros' => $filtros,
        ]);
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
