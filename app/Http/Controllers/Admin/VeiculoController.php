<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVeiculoRequest;
use App\Http\Requests\Admin\UpdateVeiculoRequest;
use App\Models\UnidadeNegocio;
use App\Models\Veiculo;
use App\Models\VeiculoHistorico;
use App\Queries\VeiculoQuery;
use App\Services\Veiculos\VeiculoAuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VeiculoController extends Controller
{
    public function __construct(
        private readonly VeiculoAuditoriaService $auditoria,
        private readonly VeiculoQuery $veiculoQuery,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->veiculoQuery->filtrosFromRequest($request);
        $veiculos = $this->veiculoQuery->aplicarFiltros(Veiculo::query(), $filtros)->get();

        return view('admin.veiculos.index', [
            'veiculos' => $veiculos,
            'filtros' => $filtros,
        ]);
    }

    public function create(): View
    {
        return view('admin.veiculos.create', [
            'veiculo' => new Veiculo([
                'status' => 'ATIVO',
            ]),
            'unidadesNegocio' => $this->unidadesParaSelect(),
        ]);
    }

    public function store(StoreVeiculoRequest $request): RedirectResponse
    {
        $user = $request->user();
        $dados = $request->validated();

        $veiculo = DB::transaction(function () use ($dados, $user) {
            $veiculo = Veiculo::create($dados);

            $this->auditoria->registrarCriacao(
                $veiculo,
                $user,
                VeiculoHistorico::ORIGEM_MANUAL,
            );

            return $veiculo;
        });

        return redirect()
            ->route('admin.veiculos.index')
            ->with('success', "Veículo \"{$veiculo->nome}\" cadastrado com sucesso.");
    }

    public function edit(Veiculo $veiculo): View
    {
        return view('admin.veiculos.edit', [
            'veiculo' => $veiculo,
            'unidadesNegocio' => $this->unidadesParaSelect(),
        ]);
    }

    public function update(UpdateVeiculoRequest $request, Veiculo $veiculo): RedirectResponse
    {
        $user = $request->user();
        $dados = $request->validated();

        DB::transaction(function () use ($veiculo, $dados, $user) {
            $antes = $this->auditoria->snapshot($veiculo);

            $veiculo->update($dados);

            $depois = $this->auditoria->snapshot($veiculo->fresh());

            $this->auditoria->registrarAtualizacao(
                $veiculo,
                $antes,
                $depois,
                $user,
                VeiculoHistorico::ORIGEM_MANUAL,
            );
        });

        return redirect()
            ->route('admin.veiculos.index')
            ->with('success', "Veículo \"{$veiculo->nome}\" atualizado com sucesso.");
    }

    public function inativar(Request $request, Veiculo $veiculo): RedirectResponse
    {
        if ($veiculo->status === 'INATIVO') {
            return redirect()
                ->route('admin.veiculos.index')
                ->with('info', "Veículo \"{$veiculo->nome}\" já estava inativo.");
        }

        $user = $request->user();

        DB::transaction(function () use ($veiculo, $user) {
            $veiculo->forceFill(['status' => 'INATIVO'])->save();

            $this->auditoria->registrarInativacao($veiculo, $user);
        });

        return redirect()
            ->route('admin.veiculos.index')
            ->with('success', "Veículo \"{$veiculo->nome}\" inativado com sucesso.");
    }

    public function reativar(Request $request, Veiculo $veiculo): RedirectResponse
    {
        if ($veiculo->status === 'ATIVO') {
            return redirect()
                ->route('admin.veiculos.index')
                ->with('info', "Veículo \"{$veiculo->nome}\" já estava ativo.");
        }

        $user = $request->user();

        DB::transaction(function () use ($veiculo, $user) {
            $veiculo->forceFill(['status' => 'ATIVO'])->save();

            $this->auditoria->registrarReativacao($veiculo, $user);
        });

        return redirect()
            ->route('admin.veiculos.index')
            ->with('success', "Veículo \"{$veiculo->nome}\" reativado com sucesso.");
    }

    public function historico(Veiculo $veiculo): View
    {
        $historicos = $veiculo->historicos()
            ->with('user')
            ->paginate(50);

        return view('admin.veiculos.historico', [
            'veiculo' => $veiculo,
            'historicos' => $historicos,
        ]);
    }

    /**
     * @return Collection<int, UnidadeNegocio>
     */
    private function unidadesParaSelect(): Collection
    {
        return UnidadeNegocio::query()
            ->orderBy('nome')
            ->get(['id', 'nome', 'id_cigam']);
    }
}
