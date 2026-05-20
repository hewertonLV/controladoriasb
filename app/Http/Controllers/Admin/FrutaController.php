<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFrutaRequest;
use App\Http\Requests\Admin\UpdateFrutaRequest;
use App\Models\Estado;
use App\Models\Fruta;
use App\Models\FrutaHistorico;
use App\Queries\FrutaQuery;
use App\Services\Frutas\FrutaAuditoriaService;
use App\Services\Frutas\FrutaIcmsSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FrutaController extends Controller
{
    public function __construct(
        private readonly FrutaAuditoriaService $auditoria,
        private readonly FrutaQuery $frutaQuery,
        private readonly FrutaIcmsSyncService $icmsSync,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->frutaQuery->filtrosFromRequest($request);
        $frutas = $this->frutaQuery->aplicarFiltros(
            Fruta::query()->withCount('icmsAliquotas'),
            $filtros,
        )->get();

        return view('admin.frutas.index', [
            'frutas' => $frutas,
            'filtros' => $filtros,
        ]);
    }

    public function create(): View
    {
        return view('admin.frutas.create', [
            'fruta' => new Fruta,
            'estados' => Estado::query()->orderBy('nome')->get(),
            'icmsForm' => $this->icmsSync->mapaParaFormulario(new Fruta),
        ]);
    }

    public function store(StoreFrutaRequest $request): RedirectResponse
    {
        $user = $request->user();

        $fruta = DB::transaction(function () use ($request, $user) {
            $fruta = Fruta::create($request->validatedFruta());
            $this->icmsSync->sync($fruta, $request->validatedIcms());
            $fruta->load('icmsAliquotas.estado');

            $this->auditoria->registrarCriacao(
                $fruta,
                $user,
                FrutaHistorico::ORIGEM_MANUAL,
            );

            return $fruta;
        });

        return redirect()
            ->route('admin.frutas.index')
            ->with('success', "Fruta \"{$fruta->nome}\" cadastrada com sucesso.");
    }

    public function edit(Fruta $fruta): View
    {
        $fruta->load('icmsAliquotas.estado');

        return view('admin.frutas.edit', [
            'fruta' => $fruta,
            'estados' => Estado::query()->orderBy('nome')->get(),
            'icmsForm' => $this->icmsSync->mapaParaFormulario($fruta),
        ]);
    }

    public function update(UpdateFrutaRequest $request, Fruta $fruta): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($request, $fruta, $user) {
            $antes = $this->auditoria->snapshot($fruta);

            $fruta->update($request->validatedFruta());
            $this->icmsSync->sync($fruta, $request->validatedIcms());
            $fruta->load('icmsAliquotas.estado');

            $depois = $this->auditoria->snapshot($fruta);

            $this->auditoria->registrarAtualizacao(
                $fruta,
                $antes,
                $depois,
                $user,
                FrutaHistorico::ORIGEM_MANUAL,
            );
        });

        return redirect()
            ->route('admin.frutas.index')
            ->with('success', "Fruta \"{$fruta->nome}\" atualizada com sucesso.");
    }

    public function historico(Fruta $fruta): View
    {
        $historicos = $fruta->historicos()
            ->with('user')
            ->paginate(50);

        return view('admin.frutas.historico', [
            'fruta' => $fruta,
            'historicos' => $historicos,
        ]);
    }
}
