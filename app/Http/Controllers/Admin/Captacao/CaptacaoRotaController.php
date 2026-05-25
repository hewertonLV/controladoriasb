<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Captacao\StoreCaptacaoRotaRequest;
use App\Http\Requests\Admin\Captacao\UpdateCaptacaoRotaRequest;
use App\Models\Captacao\CaptacaoCarteira;
use App\Models\Captacao\CaptacaoRota;
use App\Models\Veiculo;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CaptacaoRotaController extends Controller
{
    public function index(Request $request): View
    {
        $carteiraId = $request->integer('carteira') ?: null;

        $carteiras = CaptacaoCarteira::query()
            ->with(['unidadeFaturamento:id,nome', 'unidadeGalpao:id,nome'])
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'id_unidade_negocio_faturamento', 'id_unidade_negocio_galpao']);

        $query = CaptacaoRota::query()
            ->with([
                'carteira:id,nome,id_unidade_negocio_faturamento,id_unidade_negocio_galpao',
                'carteira.unidadeFaturamento:id,nome',
                'carteira.unidadeGalpao:id,nome',
                'veiculo:id,nome,id_sbs',
            ])
            ->orderBy('nome');

        if ($carteiraId !== null) {
            $query->where('id_captacao_carteira', $carteiraId);
        }

        $this->aplicarFiltroUnidadesPermitidas($request, $query);

        return view('admin.captacao.rotas.index', [
            'rotas' => $query->get(),
            'carteiras' => $carteiras,
            'carteiraId' => $carteiraId,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.captacao.rotas.create', $this->dadosFormulario($request));
    }

    public function store(StoreCaptacaoRotaRequest $request): RedirectResponse
    {
        $dados = $this->normalizarDados($request->validated(), $request);
        $this->assertAcessoCarteira($request, (int) $dados['id_captacao_carteira']);

        $rota = CaptacaoRota::query()->create($dados);

        return redirect()
            ->route('admin.captacao.rotas.index', ['carteira' => $rota->id_captacao_carteira])
            ->with('success', "Rota «{$rota->nome}» cadastrada.");
    }

    public function edit(Request $request, CaptacaoRota $rota): View
    {
        $this->assertAcessoCarteira($request, $rota->id_captacao_carteira);

        return view('admin.captacao.rotas.edit', array_merge(
            ['rota' => $rota],
            $this->dadosFormulario($request, $rota),
        ));
    }

    public function update(UpdateCaptacaoRotaRequest $request, CaptacaoRota $rota): RedirectResponse
    {
        $dados = $this->normalizarDados($request->validated(), $request);
        $this->assertAcessoCarteira($request, (int) $dados['id_captacao_carteira']);

        $rota->update($dados);

        return redirect()
            ->route('admin.captacao.rotas.index', ['carteira' => $rota->id_captacao_carteira])
            ->with('success', "Rota «{$rota->nome}» atualizada.");
    }

    /**
     * @return array<string, mixed>
     */
    private function dadosFormulario(Request $request, ?CaptacaoRota $rota = null): array
    {
        $carteiras = CaptacaoCarteira::query()
            ->with(['unidadeFaturamento:id,nome', 'unidadeGalpao:id,nome'])
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome', 'id_unidade_negocio_faturamento', 'id_unidade_negocio_galpao']);

        $veiculos = Veiculo::query()
            ->where('status', 'ATIVO')
            ->orderBy('nome')
            ->get(['id', 'id_sbs', 'nome']);

        return [
            'carteiras' => $carteiras,
            'veiculos' => $veiculos,
            'carteiraId' => $rota?->id_captacao_carteira ?? $request->integer('carteira') ?: null,
        ];
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function normalizarDados(array $dados, Request $request): array
    {
        $dados['ativo'] = $request->boolean('ativo', true);
        $dados['id_veiculo'] = ! empty($dados['id_veiculo']) ? (int) $dados['id_veiculo'] : null;

        return $dados;
    }

    private function assertAcessoCarteira(Request $request, int $carteiraId): void
    {
        $galpaoId = CaptacaoCarteira::query()
            ->whereKey($carteiraId)
            ->value('id_unidade_negocio_galpao');

        if ($galpaoId === null) {
            abort(404);
        }

        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), (int) $galpaoId)) {
            abort(403, UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO);
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<CaptacaoRota>  $query
     */
    private function aplicarFiltroUnidadesPermitidas(Request $request, $query): void
    {
        $permitidas = app(UnidadeNegocioAccessService::class)->unidadeIdsPermitidas($request->user());

        if ($permitidas !== null) {
            $query->whereHas('carteira', function ($q) use ($permitidas): void {
                $q->whereIn('id_unidade_negocio_galpao', $permitidas === [] ? [-1] : $permitidas);
            });
        }
    }
}
