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
use App\Services\Movimentacoes\FreteRateioMovimentacaoService;
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
        private readonly FreteRateioMovimentacaoService $freteRateio,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->freteQuery->filtrosFromRequest($request);
        $fretes = $this->freteQuery->aplicarFiltros(Frete::query(), $filtros)->get();

        return view('admin.fretes.index', [
            'fretes' => $fretes,
            'filtros' => $filtros,
        ]);
    }

    public function create(): View
    {
        return view('admin.fretes.create', [
            'frete' => new Frete([
                'status_situacao' => FreteStatusSituacao::ABERTA->value,
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

            if ((float) $antes['valor'] !== (float) $dados['valor']) {
                $this->freteRateio->recalcular((int) $frete->id);
            }

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
