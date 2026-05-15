<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFrutaRequest;
use App\Http\Requests\Admin\UpdateFrutaRequest;
use App\Models\Fruta;
use App\Models\FrutaHistorico;
use App\Queries\FrutaQuery;
use App\Services\Frutas\FrutaAuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FrutaController extends Controller
{
    public function __construct(
        private readonly FrutaAuditoriaService $auditoria,
        private readonly FrutaQuery $frutaQuery,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->frutaQuery->filtrosFromRequest($request);
        $query = $this->frutaQuery->aplicarFiltros(Fruta::query(), $filtros);

        if ($filtros['per_page'] === 'all') {
            $total = (clone $query)->toBase()->count();
            $resultados = $query->get();
            $frutas = $resultados;
            $exibindo = $resultados->count();
        } else {
            $paginator = $query->paginate((int) $filtros['per_page'])->appends($filtros);
            $frutas = $paginator;
            $total = $paginator->total();
            $exibindo = count((array) $paginator->items());
        }

        $payload = [
            'frutas' => $frutas,
            'filtros' => $filtros,
            'perPageOptions' => FrutaQuery::PER_PAGE_OPTIONS,
            'total' => $total,
            'exibindo' => $exibindo,
        ];

        if ($request->ajax()) {
            return view('admin.frutas._table', $payload);
        }

        return view('admin.frutas.index', $payload);
    }

    public function create(): View
    {
        return view('admin.frutas.create', [
            'fruta' => new Fruta,
        ]);
    }

    public function store(StoreFrutaRequest $request): RedirectResponse
    {
        $user = $request->user();
        $dados = $request->validated();

        $fruta = DB::transaction(function () use ($dados, $user) {
            $fruta = Fruta::create($dados);

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
        return view('admin.frutas.edit', [
            'fruta' => $fruta,
        ]);
    }

    public function update(UpdateFrutaRequest $request, Fruta $fruta): RedirectResponse
    {
        $user = $request->user();
        $dados = $request->validated();

        DB::transaction(function () use ($fruta, $dados, $user) {
            $antes = $this->auditoria->snapshot($fruta);

            $fruta->update($dados);

            $depois = $this->auditoria->snapshot($fruta->fresh());

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
