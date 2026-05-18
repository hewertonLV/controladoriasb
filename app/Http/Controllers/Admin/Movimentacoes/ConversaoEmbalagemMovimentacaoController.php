<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Enums\CategoriaMovimentacaoTipo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\StoreConversaoEmbalagemMovimentacaoRequest;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use App\Services\Movimentacoes\ConversaoEmbalagemMovimentacaoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

class ConversaoEmbalagemMovimentacaoController extends Controller
{
    public function index(): View
    {
        $query = Movimentacao::query()
            ->with(['empresaOrigem.entidade', 'fruta', 'frutaDestinoConversao', 'movimentacaoPareada.fruta'])
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::ConversaoEmbalagem->value)
            ->where('status_movimentacao_id', StatusMovimentacao::ID_SAIDA);

        $empresaIds = app(UnidadeNegocioAccessService::class)->empresaIdsPermitidas(auth()->user());
        if ($empresaIds !== null) {
            $query->whereIn('id_empresa_origem', $empresaIds);
        }

        $movimentacoes = $query
            ->latest()
            ->paginate(15);

        return view('admin.movimentacoes.conversoes-embalagem.index', compact('movimentacoes'));
    }

    public function create(ConversaoEmbalagemMovimentacaoService $service): View
    {
        return view('admin.movimentacoes.conversoes-embalagem.create', $service->opcoesFormulario());
    }

    public function store(StoreConversaoEmbalagemMovimentacaoRequest $request, ConversaoEmbalagemMovimentacaoService $service): RedirectResponse
    {
        try {
            $movimentacoes = $service->registrarConversao($request->validated(), $request->user());
        } catch (InvalidArgumentException $e) {
            return back()
                ->withInput()
                ->withErrors(['qtd_resultante_um' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.movimentacoes.conversoes-embalagem.show', $movimentacoes['saida'])
            ->with('success', 'Conversão de embalagem registrada com sucesso.');
    }

    public function show(Movimentacao $movimentacaoConversao): View
    {
        abort_unless(
            (int) $movimentacaoConversao->categoria_movimentacao_id === CategoriaMovimentacaoTipo::ConversaoEmbalagem->value
                && (int) $movimentacaoConversao->status_movimentacao_id === StatusMovimentacao::ID_SAIDA,
            404,
        );

        $movimentacaoConversao->load(['empresaOrigem.entidade', 'fruta', 'frutaDestinoConversao', 'movimentacaoPareada.fruta']);

        return view('admin.movimentacoes.conversoes-embalagem.show', [
            'movimentacao' => $movimentacaoConversao,
            'entrada' => $movimentacaoConversao->movimentacaoPareada,
        ]);
    }
}
