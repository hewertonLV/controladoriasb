<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Actions\Movimentacoes\Devolucao\AtualizarDevolucaoMovimentacaoAction;
use App\Actions\Movimentacoes\Devolucao\CancelarDevolucaoMovimentacaoAdminAction;
use App\Actions\Movimentacoes\Devolucao\RegistrarDevolucaoMovimentacaoAction;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\CancelarDevolucaoMovimentacaoAdminRequest;
use App\Http\Requests\Admin\Movimentacoes\StoreDevolucaoMovimentacaoRequest;
use App\Http\Requests\Admin\Movimentacoes\UpdateDevolucaoMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Services\Movimentacoes\DevolucaoMovimentacaoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DevolucaoMovimentacaoController extends Controller
{
    public function index(): View
    {
        $query = Movimentacao::query()
            ->with(['vendaOrigem.vendaNota', 'empresaOrigem', 'empresaDestino', 'fruta', 'unidadeRetorno'])
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Devolucao->value)
            ->where('status_registro', MovimentacaoStatusRegistro::ATIVO->value);

        $access = app(UnidadeNegocioAccessService::class);
        $unidadeIds = $access->unidadeIdsPermitidas(auth()->user());
        $empresaIds = $access->empresaIdsPermitidas(auth()->user());
        if ($unidadeIds !== null && $empresaIds !== null) {
            $query->where(function ($q) use ($empresaIds, $unidadeIds): void {
                $q->whereIn('id_unidade_negocio_retorno', $unidadeIds)
                    ->orWhereIn('id_empresa_destino', $empresaIds);
            });
        }

        $movimentacoes = $query
            ->orderByDesc('data_movimentacao')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.movimentacoes.devolucoes.index', compact('movimentacoes'));
    }

    public function create(DevolucaoMovimentacaoService $devolucoes): View
    {
        return view('admin.movimentacoes.devolucoes.create', $devolucoes->opcoesFormularioDevolucao());
    }

    public function store(StoreDevolucaoMovimentacaoRequest $request, RegistrarDevolucaoMovimentacaoAction $registrar): JsonResponse|RedirectResponse
    {
        $movimentacao = $registrar($request);

        if ($request->expectsJson()) {
            return response()->json(['data' => $movimentacao], JsonResponse::HTTP_CREATED);
        }

        return redirect()
            ->route('admin.movimentacoes.devolucoes.show', $movimentacao)
            ->with('success', 'Devolução registrada com sucesso.');
    }

    public function show(Movimentacao $movimentacaoDevolucao): View
    {
        $movimentacaoDevolucao->load(['vendaOrigem.vendaNota', 'empresaOrigem', 'empresaDestino', 'fruta', 'unidadeRetorno', 'canceladaPor']);

        return view('admin.movimentacoes.devolucoes.show', ['movimentacao' => $movimentacaoDevolucao]);
    }

    public function edit(Movimentacao $movimentacaoDevolucao, DevolucaoMovimentacaoService $devolucoes): View
    {
        $movimentacaoDevolucao->load(['vendaOrigem.vendaNota', 'empresaOrigem', 'empresaDestino', 'fruta', 'unidadeRetorno']);

        return view('admin.movimentacoes.devolucoes.edit', [
            'movimentacao' => $movimentacaoDevolucao,
            'opcoes' => $devolucoes->opcoesFormularioDevolucao(),
        ]);
    }

    public function update(
        UpdateDevolucaoMovimentacaoRequest $request,
        Movimentacao $movimentacaoDevolucao,
        AtualizarDevolucaoMovimentacaoAction $atualizar,
    ): JsonResponse|RedirectResponse {
        $nova = $atualizar($request, $movimentacaoDevolucao);

        if ($request->expectsJson()) {
            return response()->json(['data' => $nova]);
        }

        return redirect()
            ->route('admin.movimentacoes.devolucoes.show', $nova)
            ->with('success', 'Devolução atualizada (nova versão registrada).');
    }

    public function cancelarAdmin(
        CancelarDevolucaoMovimentacaoAdminRequest $request,
        Movimentacao $movimentacaoDevolucao,
        CancelarDevolucaoMovimentacaoAdminAction $cancelar,
    ): JsonResponse|RedirectResponse {
        $cancelar($request, $movimentacaoDevolucao);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Devolução cancelada administrativamente.']);
        }

        return redirect()
            ->route('admin.movimentacoes.devolucoes.index')
            ->with('success', 'Devolução cancelada administrativamente.');
    }
}
