<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AutorizaProcessamentoUsuario;
use App\Http\Controllers\Controller;
use App\Jobs\Estoques\GerarPdfEstoquesJob;
use App\Models\EstoqueExportacao;
use App\Queries\EstoqueQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EstoqueExportacaoController extends Controller
{
    use AutorizaProcessamentoUsuario;

    public function __construct(private readonly EstoqueQuery $estoqueQuery) {}

    public function iniciar(Request $request): JsonResponse
    {
        $filtros = $this->estoqueQuery->normalizarFiltros($request->only([
            'search', 'per_page', 'id_unidade_negocio', 'id_fruta', 'sort', 'direction',
        ]));

        $exportacao = EstoqueExportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $request->user()?->id,
            'tipo' => EstoqueExportacao::TIPO_PDF,
            'status' => EstoqueExportacao::STATUS_AGUARDANDO,
            'filtros' => $filtros,
        ]);

        GerarPdfEstoquesJob::dispatch($exportacao->id);

        return response()->json([
            'uuid' => $exportacao->uuid,
            'status' => $exportacao->status,
            'mensagem' => $this->mensagemDoStatus($exportacao),
            'created_at' => optional($exportacao->created_at)->toIso8601String(),
            'urls' => [
                'status' => route('admin.estoques.exportacoes.status', $exportacao, false),
                'download' => route('admin.estoques.exportacoes.download', $exportacao, false),
            ],
        ], 202);
    }

    public function status(Request $request, EstoqueExportacao $exportacao): JsonResponse
    {
        $this->autorizarAcessoProcessamento($request, $exportacao);

        return response()->json([
            'uuid' => $exportacao->uuid,
            'tipo' => $exportacao->tipo,
            'status' => $exportacao->status,
            'mensagem' => $this->mensagemDoStatus($exportacao),
            'total_registros' => $exportacao->total_registros,
            'arquivo_nome' => $exportacao->arquivo_nome,
            'erro_mensagem' => $exportacao->erro_mensagem,
            'created_at' => optional($exportacao->created_at)->toIso8601String(),
            'started_at' => optional($exportacao->started_at)->toIso8601String(),
            'finished_at' => optional($exportacao->finished_at)->toIso8601String(),
            'download_url' => $exportacao->isConcluido()
                ? route('admin.estoques.exportacoes.download', $exportacao, false)
                : null,
        ]);
    }

    public function download(Request $request, EstoqueExportacao $exportacao): BinaryFileResponse
    {
        $this->autorizarAcessoProcessamento($request, $exportacao);

        abort_unless($exportacao->isConcluido(), 409, 'O PDF ainda não está pronto para download.');
        abort_if($exportacao->arquivo_path === null, 404, 'Arquivo da exportação não encontrado.');
        abort_unless(Storage::disk('local')->exists($exportacao->arquivo_path), 404, 'Arquivo da exportação não encontrado.');

        return response()->download(
            Storage::disk('local')->path($exportacao->arquivo_path),
            $exportacao->arquivo_nome ?: 'estoques.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function mensagemDoStatus(EstoqueExportacao $exportacao): string
    {
        return match ($exportacao->status) {
            EstoqueExportacao::STATUS_AGUARDANDO => 'O PDF foi solicitado e aguarda o worker iniciar o processamento.',
            EstoqueExportacao::STATUS_PROCESSANDO => 'O PDF está sendo gerado em background.',
            EstoqueExportacao::STATUS_CONCLUIDO => 'O relatório foi gerado com sucesso.',
            EstoqueExportacao::STATUS_FALHOU => $exportacao->erro_mensagem
                ?: 'Falha ao gerar o PDF. Tente novamente.',
            default => '',
        };
    }
}
