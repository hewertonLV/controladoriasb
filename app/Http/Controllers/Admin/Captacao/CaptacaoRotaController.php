<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Captacao\StoreCaptacaoRotaRequest;
use App\Http\Requests\Admin\Captacao\UpdateCaptacaoRotaRequest;
use App\Models\Captacao\CaptacaoRota;
use App\Models\UnidadeNegocio;
use App\Models\Veiculo;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CaptacaoRotaController extends Controller
{
    public function index(Request $request): View
    {
        $galpaoId = $request->integer('galpao') ?: null;

        $galpoes = UnidadeNegocio::query()
            ->where('is_galpao_operacional', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $query = CaptacaoRota::query()
            ->with(['unidadeGalpao:id,nome', 'veiculo:id,nome,id_sbs'])
            ->orderBy('nome');

        if ($galpaoId !== null) {
            $query->where('id_unidade_negocio_galpao', $galpaoId);
        }

        $this->aplicarFiltroUnidadesPermitidas($request, $query);

        return view('admin.captacao.rotas.index', [
            'rotas' => $query->get(),
            'galpoes' => $galpoes,
            'galpaoId' => $galpaoId,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.captacao.rotas.create', $this->dadosFormulario($request));
    }

    public function store(StoreCaptacaoRotaRequest $request): RedirectResponse
    {
        $dados = $this->normalizarDados($request->validated(), $request);
        $this->assertAcessoGalpao($request, (int) $dados['id_unidade_negocio_galpao']);

        $rota = CaptacaoRota::query()->create($dados);

        return redirect()
            ->route('admin.captacao.rotas.index', ['galpao' => $rota->id_unidade_negocio_galpao])
            ->with('success', "Rota «{$rota->nome}» cadastrada.");
    }

    public function edit(Request $request, CaptacaoRota $rota): View
    {
        $this->assertAcessoGalpao($request, $rota->id_unidade_negocio_galpao);

        return view('admin.captacao.rotas.edit', array_merge(
            ['rota' => $rota],
            $this->dadosFormulario($request, $rota),
        ));
    }

    public function update(UpdateCaptacaoRotaRequest $request, CaptacaoRota $rota): RedirectResponse
    {
        $dados = $this->normalizarDados($request->validated(), $request);
        $this->assertAcessoGalpao($request, (int) $dados['id_unidade_negocio_galpao']);

        $rota->update($dados);

        return redirect()
            ->route('admin.captacao.rotas.index', ['galpao' => $rota->id_unidade_negocio_galpao])
            ->with('success', "Rota «{$rota->nome}» atualizada.");
    }

    /**
     * @return array<string, mixed>
     */
    private function dadosFormulario(Request $request, ?CaptacaoRota $rota = null): array
    {
        $galpoes = UnidadeNegocio::query()
            ->where('is_galpao_operacional', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        $veiculos = Veiculo::query()
            ->where('status', 'ATIVO')
            ->orderBy('nome')
            ->get(['id', 'id_sbs', 'nome']);

        return [
            'galpoes' => $galpoes,
            'veiculos' => $veiculos,
            'galpaoId' => $rota?->id_unidade_negocio_galpao ?? $request->integer('galpao') ?: null,
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

    private function assertAcessoGalpao(Request $request, int $galpaoId): void
    {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $galpaoId)) {
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
            $query->whereIn('id_unidade_negocio_galpao', $permitidas === [] ? [-1] : $permitidas);
        }
    }
}
