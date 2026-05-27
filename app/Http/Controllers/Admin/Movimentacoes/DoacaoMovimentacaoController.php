<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Actions\Movimentacoes\Doacao\AtualizarDoacaoMovimentacaoAction;
use App\Actions\Movimentacoes\Doacao\CriarDoacaoMovimentacaoAction;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\StoreDoacaoMovimentacaoRequest;
use App\Http\Requests\Admin\Movimentacoes\UpdateDoacaoMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use App\Services\Movimentacoes\DoacaoMovimentacaoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DoacaoMovimentacaoController extends Controller
{
    public function index(): View
    {
        $query = Movimentacao::query()
            ->with(['empresaOrigem', 'empresaDestino', 'fruta'])
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Doacao->value)
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

        return view('admin.movimentacoes.doacoes.index', [
            'movimentacoes' => $movimentacoes,
        ]);
    }

    public function create(DoacaoMovimentacaoService $doacoes): View
    {
        return view('admin.movimentacoes.doacoes.create', $doacoes->opcoesFormularioDoacao());
    }

    public function store(
        StoreDoacaoMovimentacaoRequest $request,
        CriarDoacaoMovimentacaoAction $criar,
    ): JsonResponse|RedirectResponse {
        $movimentacoes = $criar($request);
        $movimentacao = $movimentacoes->firstOrFail();

        if ($request->expectsJson()) {
            return response()->json(['data' => $movimentacoes->count() === 1 ? $movimentacao : $movimentacoes], JsonResponse::HTTP_CREATED);
        }

        return redirect()
            ->route('admin.movimentacoes.doacoes.show', $movimentacao)
            ->with('success', $movimentacoes->count() > 1 ? 'Doações registradas com sucesso.' : 'Doação registrada com sucesso.');
    }

    public function show(Movimentacao $movimentacaoDoacao): View
    {
        $movimentacaoDoacao->load(['empresaOrigem', 'empresaDestino', 'fruta', 'canceladaPor']);

        return view('admin.movimentacoes.doacoes.show', [
            'movimentacao' => $movimentacaoDoacao,
        ]);
    }

    public function edit(Movimentacao $movimentacaoDoacao): View
    {
        $movimentacaoDoacao->load(['empresaOrigem', 'empresaDestino', 'fruta']);

        return view('admin.movimentacoes.doacoes.edit', [
            'movimentacao' => $movimentacaoDoacao,
            'opcoes' => app(DoacaoMovimentacaoService::class)->opcoesFormularioDoacao(),
        ]);
    }

    public function update(
        UpdateDoacaoMovimentacaoRequest $request,
        Movimentacao $movimentacaoDoacao,
        AtualizarDoacaoMovimentacaoAction $atualizar,
    ): JsonResponse|RedirectResponse {
        $nova = $atualizar($request, $movimentacaoDoacao);

        if ($request->expectsJson()) {
            return response()->json(['data' => $nova]);
        }

        return redirect()
            ->route('admin.movimentacoes.doacoes.show', $nova)
            ->with('success', 'Doação atualizada (nova versão registrada).');
    }
}
