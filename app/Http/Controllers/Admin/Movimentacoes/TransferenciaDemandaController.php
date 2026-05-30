<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Captacao\UploadDemandaTransferenciaNfRequest;
use App\Http\Requests\Admin\Movimentacoes\StoreTransferenciaDemandaManualRequest;
use App\Models\Fruta;
use App\Models\Movimentacoes\TransferenciaDemanda;
use App\Models\UnidadeNegocio;
use App\Services\Movimentacoes\TransferenciaDemandaManualService;
use App\Services\Movimentacoes\TransferenciaMovimentacaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransferenciaDemandaController extends Controller
{
    public function index(): View
    {
        $demandas = TransferenciaDemanda::query()
            ->with(['unidadeOrigem', 'unidadeDestino', 'linhas.fruta'])
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return view('admin.movimentacoes.transferencias.demandas.index', [
            'demandas' => $demandas,
        ]);
    }

    public function create(TransferenciaMovimentacaoService $transferencias): View
    {
        $opcoes = $transferencias->opcoesFormularioTransferencia();

        return view('admin.movimentacoes.transferencias.demandas.create', [
            'unidades' => $opcoes['unidades'] ?? UnidadeNegocio::query()->orderBy('nome')->get(),
            'frutas' => Fruta::query()->orderBy('nome')->get(['id', 'nome', 'unidade_medicao']),
        ]);
    }

    public function store(
        StoreTransferenciaDemandaManualRequest $request,
        TransferenciaDemandaManualService $service,
    ): RedirectResponse {
        $demanda = $service->salvar($request->validated());

        return redirect()
            ->route('admin.movimentacoes.transferencias.demandas.edit', $demanda)
            ->with('success', 'Demanda salva.');
    }

    public function edit(TransferenciaDemanda $demanda): View
    {
        $demanda->load(['linhas.fruta', 'unidadeOrigem', 'unidadeDestino']);

        return view('admin.movimentacoes.transferencias.demandas.edit', [
            'demanda' => $demanda,
            'unidades' => UnidadeNegocio::query()->orderBy('nome')->get(),
            'frutas' => Fruta::query()->orderBy('nome')->get(['id', 'nome', 'unidade_medicao']),
            'fretesAbertos' => \App\Models\Frete::query()
                ->where('status_situacao', \App\Enums\FreteStatusSituacao::Aberto->value)
                ->orderByDesc('id')
                ->limit(50)
                ->get(),
        ]);
    }

    public function update(
        StoreTransferenciaDemandaManualRequest $request,
        TransferenciaDemanda $demanda,
        TransferenciaDemandaManualService $service,
    ): RedirectResponse {
        $service->salvar($request->validated(), $demanda);

        return back()->with('success', 'Demanda atualizada.');
    }

    public function iniciar(TransferenciaDemanda $demanda, TransferenciaDemandaManualService $service): RedirectResponse
    {
        $service->iniciar($demanda);

        return back()->with('success', 'Transferência iniciada.');
    }

    public function uploadNf(
        UploadDemandaTransferenciaNfRequest $request,
        TransferenciaDemanda $demanda,
        TransferenciaDemandaManualService $service,
    ): RedirectResponse {
        $service->anexarNf($demanda, $request->file('arquivo_nf'));

        return back()->with('success', 'NF anexada. Vincule o frete ou escolha «Sem frete».');
    }

    public function concluirFrete(Request $request, TransferenciaDemanda $demanda, TransferenciaDemandaManualService $service): RedirectResponse
    {
        $idFrete = $request->input('sem_frete') ? null : ($request->input('id_frete') ? (int) $request->input('id_frete') : null);

        if (! $request->boolean('sem_frete') && $idFrete === null) {
            return back()->withErrors(['id_frete' => 'Selecione um frete ou marque «Sem frete».']);
        }

        $service->concluirComFrete($demanda, $idFrete);

        return redirect()
            ->route('admin.movimentacoes.transferencias.demandas.index')
            ->with('success', 'Demanda concluída e movimentação gerada.');
    }

    public function destroy(TransferenciaDemanda $demanda, TransferenciaDemandaManualService $service): RedirectResponse
    {
        $service->excluir($demanda);

        return redirect()
            ->route('admin.movimentacoes.transferencias.demandas.index')
            ->with('success', 'Demanda excluída.');
    }
}
