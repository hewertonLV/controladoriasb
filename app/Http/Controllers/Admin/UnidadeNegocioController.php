<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUnidadeNegocioRequest;
use App\Http\Requests\Admin\UpdateUnidadeNegocioRequest;
use App\Models\Estado;
use App\Models\UnidadeNegocio;
use App\Models\UnidadeNegocioHistorico;
use App\Queries\UnidadeNegocioQuery;
use App\Services\UnidadesNegocio\UnidadeNegocioAuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UnidadeNegocioController extends Controller
{
    public function __construct(
        private readonly UnidadeNegocioAuditoriaService $auditoria,
        private readonly UnidadeNegocioQuery $unidadeNegocioQuery,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->unidadeNegocioQuery->filtrosFromRequest($request);
        $unidadesNegocio = $this->unidadeNegocioQuery->aplicarFiltros(
            UnidadeNegocio::query(),
            $filtros,
        )->get();

        return view('admin.unidades-negocio.index', [
            'unidadesNegocio' => $unidadesNegocio,
            'filtros' => $filtros,
            'estados' => Estado::query()->orderBy('nome')->get(['id', 'nome', 'abreviacao']),
        ]);
    }

    public function create(): View
    {
        return view('admin.unidades-negocio.create', [
            'estados' => Estado::query()->orderBy('nome')->get(['id', 'nome', 'abreviacao']),
            'unidadeNegocio' => new UnidadeNegocio([
                'status' => true,
                'possui_estoque' => false,
                'custo_operacional' => '0.00',
                'id_estado' => Estado::ID_CEARA,
            ]),
        ]);
    }

    public function store(StoreUnidadeNegocioRequest $request): RedirectResponse
    {
        $user = $request->user();
        $dados = $request->validated();

        $unidade = DB::transaction(function () use ($dados, $user) {
            $unidade = UnidadeNegocio::create($dados + [
                'status' => true,
            ]);

            $this->auditoria->registrarCriacao(
                $unidade,
                $user,
                UnidadeNegocioHistorico::ORIGEM_MANUAL,
            );

            return $unidade;
        });

        return redirect()
            ->route('admin.unidades-negocio.index')
            ->with('success', "Unidade de negócio \"{$unidade->nome}\" cadastrada com sucesso.");
    }

    public function edit(UnidadeNegocio $unidadeNegocio): View
    {
        $unidadeNegocio->load([
            'historicoCustoOperacionalAtual',
            'historicosCustoOperacional',
        ]);

        return view('admin.unidades-negocio.edit', [
            'estados' => Estado::query()->orderBy('nome')->get(['id', 'nome', 'abreviacao']),
            'unidadeNegocio' => $unidadeNegocio,
        ]);
    }

    public function update(UpdateUnidadeNegocioRequest $request, UnidadeNegocio $unidadeNegocio): RedirectResponse
    {
        $user = $request->user();
        $dados = $request->validated();

        DB::transaction(function () use ($unidadeNegocio, $dados, $user) {
            $antes = $this->auditoria->snapshot($unidadeNegocio);

            $unidadeNegocio->update($dados);

            $depois = $this->auditoria->snapshot($unidadeNegocio->fresh());

            $this->auditoria->registrarAtualizacao(
                $unidadeNegocio,
                $antes,
                $depois,
                $user,
                UnidadeNegocioHistorico::ORIGEM_MANUAL,
            );
        });

        return redirect()
            ->route('admin.unidades-negocio.index')
            ->with('success', "Unidade de negócio \"{$unidadeNegocio->nome}\" atualizada com sucesso.");
    }

    public function inativar(Request $request, UnidadeNegocio $unidadeNegocio): RedirectResponse
    {
        if (! $unidadeNegocio->status) {
            return redirect()
                ->route('admin.unidades-negocio.index')
                ->with('info', 'Esta Unidade de Negócio já está inativa.');
        }

        $user = $request->user();

        DB::transaction(function () use ($unidadeNegocio, $user) {
            $unidadeNegocio->forceFill(['status' => false])->save();

            $this->auditoria->registrarInativacao($unidadeNegocio, $user);
        });

        return redirect()
            ->route('admin.unidades-negocio.index')
            ->with('success', 'Unidade de Negócio inativada com sucesso.');
    }

    public function ativar(Request $request, UnidadeNegocio $unidadeNegocio): RedirectResponse
    {
        if ($unidadeNegocio->status) {
            return redirect()
                ->route('admin.unidades-negocio.index')
                ->with('info', 'Esta Unidade de Negócio já está ativa.');
        }

        $user = $request->user();

        DB::transaction(function () use ($unidadeNegocio, $user) {
            $unidadeNegocio->forceFill(['status' => true])->save();

            $this->auditoria->registrarReativacao($unidadeNegocio, $user);
        });

        return redirect()
            ->route('admin.unidades-negocio.index')
            ->with('success', 'Unidade de Negócio ativada com sucesso.');
    }

    public function historico(UnidadeNegocio $unidadeNegocio): View
    {
        $unidadeNegocio->load('estado');

        $historicos = $unidadeNegocio->historicos()
            ->with('user')
            ->paginate(50);

        return view('admin.unidades-negocio.historico', [
            'unidadeNegocio' => $unidadeNegocio,
            'historicos' => $historicos,
            'estadosPorId' => Estado::query()->pluck('nome', 'id')->all(),
        ]);
    }
}
