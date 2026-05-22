<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Actions\Movimentacoes\EntradaEstoque\CriarEntradaEstoqueMovimentacaoAction;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\StoreEntradaEstoqueMovimentacaoRequest;
use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use App\Models\UnidadeNegocio;
use App\Services\Movimentacoes\EntradaEstoqueMovimentacaoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EntradaEstoqueMovimentacaoController extends Controller
{
    public function index(): View
    {
        $query = Movimentacao::query()
            ->with(['empresaOrigem', 'fruta'])
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::EntradaEstoque->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_ENTRADA)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value);

        $empresaIds = app(UnidadeNegocioAccessService::class)->empresaIdsPermitidas(auth()->user());
        if ($empresaIds !== null) {
            $query->whereIn('id_empresa_origem', $empresaIds);
        }

        $movimentacoes = $query
            ->orderByDesc('data_movimentacao')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.movimentacoes.entradas-estoque.index', [
            'movimentacoes' => $movimentacoes,
        ]);
    }

    public function create(EntradaEstoqueMovimentacaoService $service): View
    {
        return view('admin.movimentacoes.entradas-estoque.create', $service->opcoesFormulario());
    }

    public function store(
        StoreEntradaEstoqueMovimentacaoRequest $request,
        CriarEntradaEstoqueMovimentacaoAction $criar,
    ): JsonResponse|RedirectResponse {
        $movimentacoes = $criar($request);
        $movimentacao = $movimentacoes->firstOrFail();

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $movimentacoes->count() === 1 ? $movimentacao : $movimentacoes,
            ], JsonResponse::HTTP_CREATED);
        }

        return redirect()
            ->route('admin.movimentacoes.entradas-estoque.show', $movimentacao)
            ->with('success', $movimentacoes->count() > 1
                ? 'Entradas de estoque registradas com sucesso.'
                : 'Entrada de estoque registrada com sucesso.');
    }

    public function show(Movimentacao $movimentacaoEntradaEstoque): View
    {
        $movimentacaoEntradaEstoque->load(['empresaOrigem', 'fruta', 'canceladaPor']);

        $estoque = null;
        $empresa = $movimentacaoEntradaEstoque->empresaOrigem;
        if ($empresa !== null) {
            $unidade = Empresa::query()->with('entidade')->find($empresa->id)?->entidade;
            if ($unidade instanceof UnidadeNegocio) {
                $estoque = Estoque::query()
                    ->where('id_unidade_negocio', $unidade->id)
                    ->where('id_fruta', $movimentacaoEntradaEstoque->id_fruta)
                    ->first();
            }
        }

        return view('admin.movimentacoes.entradas-estoque.show', [
            'movimentacao' => $movimentacaoEntradaEstoque,
            'estoque' => $estoque,
        ]);
    }
}
