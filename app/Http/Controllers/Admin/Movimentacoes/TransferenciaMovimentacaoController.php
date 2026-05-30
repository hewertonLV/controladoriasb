<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Actions\Movimentacoes\Transferencia\CancelarTransferenciaAction;
use App\Actions\Movimentacoes\Transferencia\ConfirmarRecebimentoTransferenciaAction;
use App\Actions\Movimentacoes\Transferencia\CriarTransferenciaMovimentacaoAction;
use App\Actions\Movimentacoes\Transferencia\VincularFreteTransferenciaAction;
use App\Enums\CategoriaMovimentacaoTipo;
use App\Enums\MovimentacaoStatusRegistro;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Movimentacoes\CancelarTransferenciaRequest;
use App\Http\Requests\Admin\Movimentacoes\ConfirmarRecebimentoTransferenciaRequest;
use App\Http\Requests\Admin\Movimentacoes\VincularFreteTransferenciaRequest;
use App\Http\Requests\Admin\Movimentacoes\StoreTransferenciaMovimentacaoRequest;
use App\Enums\FreteStatusSituacao;
use App\Models\Frete;
use App\Models\Movimentacao;
use App\Models\StatusMovimentacao;
use App\Services\Captacao\CaptacaoDemandasRotaExibicaoService;
use App\Services\Movimentacoes\TransferenciaMovimentacaoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class TransferenciaMovimentacaoController extends Controller
{
    public function index(): View
    {
        $query = Movimentacao::query()
            ->with(['empresaOrigem', 'empresaDestino', 'fruta', 'frete'])
            ->where('categoria_movimentacao_id', CategoriaMovimentacaoTipo::Transferencia->value)
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

        return view('admin.movimentacoes.transferencias.index', [
            'movimentacoes' => $movimentacoes,
            'demandasCards' => app(CaptacaoDemandasRotaExibicaoService::class)
                ->cardsTransferenciaModulo(auth()->user()),
        ]);
    }

    public function create(TransferenciaMovimentacaoService $transferencias): View
    {
        return view('admin.movimentacoes.transferencias.create', $transferencias->opcoesFormularioTransferencia());
    }

    public function store(
        StoreTransferenciaMovimentacaoRequest $request,
        CriarTransferenciaMovimentacaoAction $criar,
    ): JsonResponse|RedirectResponse {
        try {
            $pares = $criar($request);
        } catch (InvalidArgumentException $e) {
            $campo = str_contains($e->getMessage(), 'nunca recebeu') ? 'id_fruta' : 'id_empresa_origem';

            if ($request->expectsJson()) {
                throw ValidationException::withMessages([
                    $campo => $e->getMessage(),
                ]);
            }

            return back()
                ->withInput()
                ->withErrors([$campo => $e->getMessage()]);
        }

        $par = $pares->firstOrFail();
        $anchor = (int) $par['saida']->transferencia_origem_id;

        if ($request->expectsJson()) {
            return response()->json(['data' => $pares->count() === 1 ? $par : $pares], JsonResponse::HTTP_CREATED);
        }

        return redirect()
            ->route('admin.movimentacoes.transferencias.show', ['transferenciaOrigem' => $anchor])
            ->with('success', $pares->count() > 1
                ? 'Transferências registradas (saída na origem e entrada no destino).'
                : 'Transferência registrada (saída na origem e entrada no destino).');
    }

    public function show(Movimentacao $transferenciaOrigem): View
    {
        $saida = $transferenciaOrigem->load([
            'empresaOrigem',
            'empresaDestino',
            'fruta',
            'frete',
            'movimentacaoPareada.custoOperacionalHistorico',
            'canceladaPor',
            'movimentacaoPareada.canceladaPor',
        ]);

        $entrada = $saida->movimentacaoPareada;
        abort_if($entrada === null, 404);

        return view('admin.movimentacoes.transferencias.show', [
            'saida' => $saida,
            'entrada' => $entrada,
            'fretes' => Frete::query()
                ->where('status_situacao', FreteStatusSituacao::ABERTA->value)
                ->orderBy('nome')
                ->get(),
        ]);
    }

    public function vincularFrete(
        VincularFreteTransferenciaRequest $request,
        Movimentacao $transferenciaOrigem,
        VincularFreteTransferenciaAction $vincular,
    ): JsonResponse|RedirectResponse {
        $anchor = (int) $transferenciaOrigem->transferencia_origem_id;

        try {
            $vincular($request, $anchor);
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                throw ValidationException::withMessages([
                    'id_frete' => $e->getMessage(),
                ]);
            }

            return back()
                ->withInput()
                ->withErrors(['id_frete' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Frete atualizado e lançamentos recalculados.']);
        }

        return redirect()
            ->route('admin.movimentacoes.transferencias.show', ['transferenciaOrigem' => $anchor])
            ->with('success', 'Frete atualizado. Rateio e estoque do destino foram recalculados.');
    }

    public function confirmarRecebimento(
        ConfirmarRecebimentoTransferenciaRequest $request,
        Movimentacao $transferenciaOrigem,
        ConfirmarRecebimentoTransferenciaAction $confirmar,
    ): JsonResponse|RedirectResponse {
        $anchor = (int) $transferenciaOrigem->transferencia_origem_id;
        $confirmar($request, $anchor);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Recebimento confirmado. Estoque creditado no destino.']);
        }

        return redirect()
            ->route('admin.movimentacoes.transferencias.show', ['transferenciaOrigem' => $anchor])
            ->with('success', 'Recebimento confirmado. Vendas vinculadas foram concluídas quando aplicável.');
    }

    public function cancelar(
        CancelarTransferenciaRequest $request,
        Movimentacao $transferenciaOrigem,
        CancelarTransferenciaAction $cancelar,
    ): JsonResponse|RedirectResponse {
        $anchor = (int) $transferenciaOrigem->transferencia_origem_id;
        $cancelar($request, $anchor);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Transferência cancelada.']);
        }

        return redirect()
            ->route('admin.movimentacoes.transferencias.index')
            ->with('success', 'Transferência cancelada; estoques de origem e destino estornados.');
    }
}
