<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Actions\Movimentacoes\Compra\AtualizarCompraMovimentacaoAction;
use App\Actions\Movimentacoes\Compra\CriarCompraMovimentacaoAction;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\StoreCompraMovimentacaoRequest;
use App\Http\Requests\Admin\Movimentacoes\UpdateCompraMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\CompraMovimentacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompraMovimentacaoController extends Controller
{
    public function index(): View
    {
        $movimentacoes = Movimentacao::query()
            ->with(['empresaOrigem', 'empresaDestino', 'fruta', 'frete'])
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Compra->value)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value)
            ->orderByDesc('data_movimentacao')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.movimentacoes.compras.index', [
            'movimentacoes' => $movimentacoes,
        ]);
    }

    public function create(CompraMovimentacaoService $compra): View
    {
        return view('admin.movimentacoes.compras.create', $compra->opcoesFormularioCompra());
    }

    public function store(
        StoreCompraMovimentacaoRequest $request,
        CriarCompraMovimentacaoAction $criar,
    ): JsonResponse|RedirectResponse {
        $movimentacao = $criar($request);

        if ($request->expectsJson()) {
            return response()->json(['data' => $movimentacao], JsonResponse::HTTP_CREATED);
        }

        return redirect()
            ->route('admin.movimentacoes.compras.show', $movimentacao)
            ->with('success', 'Compra registrada com sucesso.');
    }

    public function show(Movimentacao $movimentacao): View
    {
        $movimentacao->load(['empresaOrigem', 'empresaDestino', 'fruta', 'frete', 'custoOperacionalHistorico']);

        return view('admin.movimentacoes.compras.show', [
            'movimentacao' => $movimentacao,
        ]);
    }

    public function edit(Movimentacao $movimentacao): View
    {
        $movimentacao->load(['empresaOrigem', 'empresaDestino', 'fruta', 'frete']);

        return view('admin.movimentacoes.compras.edit', [
            'movimentacao' => $movimentacao,
        ]);
    }

    public function update(
        UpdateCompraMovimentacaoRequest $request,
        Movimentacao $movimentacao,
        AtualizarCompraMovimentacaoAction $atualizar,
    ): JsonResponse|RedirectResponse {
        $nova = $atualizar($movimentacao, $request);

        if ($request->expectsJson()) {
            return response()->json(['data' => $nova]);
        }

        return redirect()
            ->route('admin.movimentacoes.compras.show', $nova)
            ->with('success', 'Compra atualizada (nova versão registrada).');
    }
}
