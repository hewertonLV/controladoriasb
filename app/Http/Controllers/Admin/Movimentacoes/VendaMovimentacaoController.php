<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Actions\Movimentacoes\Venda\AtualizarVendaMovimentacaoAction;
use App\Actions\Movimentacoes\Venda\CriarVendaMovimentacaoAction;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\StoreVendaMovimentacaoRequest;
use App\Http\Requests\Admin\Movimentacoes\UpdateVendaMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use App\Services\Captacao\CaptacaoDemandasRotaExibicaoService;
use App\Services\Movimentacoes\VendaMovimentacaoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VendaMovimentacaoController extends Controller
{
    public function index(): View
    {
        $query = Movimentacao::query()
            ->with(['vendaNota', 'empresaOrigem', 'empresaDestino', 'unidadeFaturamento', 'fruta'])
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
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

        return view('admin.movimentacoes.vendas.index', [
            'movimentacoes' => $movimentacoes,
            'demandasCards' => app(CaptacaoDemandasRotaExibicaoService::class)
                ->cardsVendaModulo(auth()->user()),
        ]);
    }

    public function create(VendaMovimentacaoService $vendas): View
    {
        return view('admin.movimentacoes.vendas.create', [
            'opcoes' => $vendas->opcoesFormularioVenda(),
        ]);
    }

    public function store(StoreVendaMovimentacaoRequest $request, CriarVendaMovimentacaoAction $criar): JsonResponse|RedirectResponse
    {
        $resultado = $criar($request);
        $primeira = $resultado['movimentacoes']->first();

        if ($request->expectsJson()) {
            return response()->json(['data' => $resultado], JsonResponse::HTTP_CREATED);
        }

        return redirect()
            ->route('admin.movimentacoes.vendas.show', $primeira)
            ->with('success', 'Venda registrada com sucesso.');
    }

    public function show(Movimentacao $movimentacaoVenda): View
    {
        $movimentacaoVenda->load(['vendaNota', 'empresaOrigem', 'empresaDestino', 'unidadeFaturamento', 'unidadeEstoque', 'fruta', 'canceladaPor']);
        $itens = $this->itensDaMesmaVenda($movimentacaoVenda);

        return view('admin.movimentacoes.vendas.show', [
            'movimentacao' => $movimentacaoVenda,
            'itens' => $itens,
        ]);
    }

    public function edit(Movimentacao $movimentacaoVenda): View
    {
        $movimentacaoVenda->load(['vendaNota', 'empresaOrigem', 'empresaDestino', 'unidadeFaturamento', 'fruta']);

        return view('admin.movimentacoes.vendas.edit', [
            'movimentacao' => $movimentacaoVenda,
            'itens' => $this->itensDaMesmaVenda($movimentacaoVenda),
            'opcoes' => app(VendaMovimentacaoService::class)->opcoesFormularioVenda(),
        ]);
    }

    public function update(
        UpdateVendaMovimentacaoRequest $request,
        Movimentacao $movimentacaoVenda,
        AtualizarVendaMovimentacaoAction $atualizar,
    ): JsonResponse|RedirectResponse {
        $nova = $atualizar($request, $movimentacaoVenda);

        if ($request->expectsJson()) {
            return response()->json(['data' => $nova]);
        }

        return redirect()
            ->route('admin.movimentacoes.vendas.show', $nova)
            ->with('success', 'Venda atualizada (nova versão registrada).');
    }

    /**
     * @return Collection<int, Movimentacao>
     */
    private function itensDaMesmaVenda(Movimentacao $movimentacao): Collection
    {
        $statusExibicao = $movimentacao->status_registro === MovimentacaoStatusRegistro::SUBSTITUIDO->value
            ? MovimentacaoStatusRegistro::ATIVO->value
            : $movimentacao->status_registro;

        $query = Movimentacao::query()
            ->with(['vendaNota', 'empresaOrigem', 'empresaDestino', 'unidadeFaturamento', 'unidadeEstoque', 'fruta', 'canceladaPor'])
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Venda->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA)
            ->whereIn('status_registro', [
                MovimentacaoStatusRegistro::ATIVO->value,
                MovimentacaoStatusRegistro::CANCELADO->value,
                $statusExibicao,
            ])
            ->orderBy('id');

        if ($movimentacao->venda_nota_id !== null) {
            $query->where('venda_nota_id', $movimentacao->venda_nota_id);
        } else {
            $query->whereKey($movimentacao->id);
        }

        return $query->get();
    }
}
