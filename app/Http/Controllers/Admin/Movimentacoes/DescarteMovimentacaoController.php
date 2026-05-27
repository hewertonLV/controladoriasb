<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Actions\Movimentacoes\Descarte\AtualizarDescarteMovimentacaoAction;
use App\Actions\Movimentacoes\Descarte\CriarDescarteMovimentacaoAction;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\StoreDescarteMovimentacaoRequest;
use App\Http\Requests\Admin\Movimentacoes\UpdateDescarteMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use App\Services\Movimentacoes\DescarteMovimentacaoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DescarteMovimentacaoController extends Controller
{
    public function index(): View
    {
        $query = Movimentacao::query()
            ->with(['empresaOrigem', 'fruta', 'categoriaDescarte'])
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Descarte->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value);

        $empresaIds = app(UnidadeNegocioAccessService::class)->empresaIdsPermitidas(auth()->user());
        if ($empresaIds !== null) {
            $query->whereIn('id_empresa_origem', $empresaIds);
        }

        $movimentacoes = $query
            ->orderByDesc('data_movimentacao')
            ->orderByDesc('id')
            ->get();

        return view('admin.movimentacoes.descartes.index', [
            'movimentacoes' => $movimentacoes,
        ]);
    }

    public function create(DescarteMovimentacaoService $descartes): View
    {
        return view('admin.movimentacoes.descartes.create', $descartes->opcoesFormularioDescarte());
    }

    public function store(
        StoreDescarteMovimentacaoRequest $request,
        CriarDescarteMovimentacaoAction $criar,
    ): JsonResponse|RedirectResponse {
        $movimentacoes = $criar($request);
        $movimentacao = $movimentacoes->firstOrFail();

        if ($request->expectsJson()) {
            return response()->json(['data' => $movimentacoes->count() === 1 ? $movimentacao : $movimentacoes], JsonResponse::HTTP_CREATED);
        }

        return redirect()
            ->route('admin.movimentacoes.descartes.show', $movimentacao)
            ->with('success', $movimentacoes->count() > 1 ? 'Descartes registrados com sucesso.' : 'Descarte registrado com sucesso.');
    }

    public function show(Movimentacao $movimentacaoDescarte): View
    {
        $movimentacaoDescarte->load(['empresaOrigem', 'fruta', 'categoriaDescarte', 'canceladaPor']);

        return view('admin.movimentacoes.descartes.show', [
            'movimentacao' => $movimentacaoDescarte,
        ]);
    }

    public function edit(Movimentacao $movimentacaoDescarte): View
    {
        $movimentacaoDescarte->load(['empresaOrigem', 'fruta', 'categoriaDescarte']);

        return view('admin.movimentacoes.descartes.edit', [
            'movimentacao' => $movimentacaoDescarte,
            'opcoes' => app(DescarteMovimentacaoService::class)->opcoesFormularioDescarte(),
        ]);
    }

    public function update(
        UpdateDescarteMovimentacaoRequest $request,
        Movimentacao $movimentacaoDescarte,
        AtualizarDescarteMovimentacaoAction $atualizar,
    ): JsonResponse|RedirectResponse {
        $nova = $atualizar($request, $movimentacaoDescarte);

        if ($request->expectsJson()) {
            return response()->json(['data' => $nova]);
        }

        return redirect()
            ->route('admin.movimentacoes.descartes.show', $nova)
            ->with('success', 'Descarte atualizado (nova versão registrada).');
    }
}
