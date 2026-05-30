<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Captacao\UploadDemandaTransferenciaNfRequest;
use App\Models\Captacao\CaptacaoLote;
use App\Models\Captacao\CaptacaoLoteMovimentacao;
use App\Services\Captacao\CaptacaoDemandaTransferenciaRotaService;
use App\Services\Captacao\CaptacaoDemandaVendaRotaService;
use App\Services\Captacao\CaptacaoMatrizEstadoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class CaptacaoDemandaRotaController extends Controller
{
    public function __construct(
        private readonly CaptacaoDemandaTransferenciaRotaService $transferencias,
        private readonly CaptacaoDemandaVendaRotaService $vendas,
        private readonly CaptacaoMatrizEstadoService $estado,
    ) {}

    public function iniciarTransferencia(Request $request, CaptacaoLote $lote, CaptacaoLoteMovimentacao $demanda): JsonResponse
    {
        $this->autorizarLote($request, $lote);
        $this->assertDemandaDoLote($lote, $demanda);

        try {
            $this->transferencias->iniciar($demanda);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'errors' => $e->errors(),
            ], 422);
        }

        return $this->respostaEstado($lote);
    }

    public function downloadCigamTransferencia(Request $request, CaptacaoLote $lote, CaptacaoLoteMovimentacao $demanda): Response
    {
        $this->autorizarLote($request, $lote);
        $this->assertDemandaDoLote($lote, $demanda);

        $conteudo = $this->transferencias->gerarArquivoCigam($demanda);

        return response($conteudo, 200, [
            'Content-Type' => 'text/plain; charset=Windows-1252',
            'Content-Disposition' => 'attachment; filename="cigam-demanda-'.$demanda->id.'.txt"',
        ]);
    }

    public function uploadNfTransferencia(
        UploadDemandaTransferenciaNfRequest $request,
        CaptacaoLote $lote,
        CaptacaoLoteMovimentacao $demanda,
    ): JsonResponse {
        $this->autorizarLote($request, $lote);
        $this->assertDemandaDoLote($lote, $demanda);

        try {
            $this->transferencias->anexarNfEConcluir($demanda, $request->file('arquivo_nf'));
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return $this->respostaEstado($lote);
    }

    public function excluirTransferencia(Request $request, CaptacaoLote $lote, CaptacaoLoteMovimentacao $demanda): JsonResponse
    {
        $this->autorizarLote($request, $lote);
        $this->assertDemandaDoLote($lote, $demanda);

        try {
            $this->transferencias->excluir($demanda);
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return $this->respostaEstado($lote);
    }

    public function efetivarVenda(Request $request, CaptacaoLote $lote, CaptacaoLoteMovimentacao $demanda): JsonResponse
    {
        $this->autorizarLote($request, $lote);
        $this->assertDemandaDoLote($lote, $demanda);

        try {
            $this->vendas->efetivar($demanda, $request->user());
        } catch (ValidationException $e) {
            return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
        }

        return $this->respostaEstado($lote);
    }

    public function downloadCigamVenda(Request $request, CaptacaoLote $lote, CaptacaoLoteMovimentacao $demanda): Response
    {
        $this->autorizarLote($request, $lote);
        $this->assertDemandaDoLote($lote, $demanda);

        $conteudo = $this->vendas->gerarArquivoCigam($demanda);

        return response($conteudo, 200, [
            'Content-Type' => 'text/plain; charset=Windows-1252',
            'Content-Disposition' => 'attachment; filename="cigam-venda-demanda-'.$demanda->id.'.txt"',
        ]);
    }

    private function respostaEstado(CaptacaoLote $lote): JsonResponse
    {
        return response()->json($this->estado->snapshot($lote->fresh()));
    }

    private function autorizarLote(Request $request, CaptacaoLote $lote): void
    {
        if (! app(UnidadeNegocioAccessService::class)->canAccess($request->user(), $lote->id_unidade_negocio_galpao)) {
            abort(403);
        }
    }

    private function assertDemandaDoLote(CaptacaoLote $lote, CaptacaoLoteMovimentacao $demanda): void
    {
        if ((int) $demanda->id_captacao_lote !== (int) $lote->id) {
            abort(404);
        }
    }
}
