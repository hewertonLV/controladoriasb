<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AutorizaProcessamentoUsuario;
use App\Http\Controllers\Controller;
use App\Jobs\Grupos\GerarPdfGruposJob;
use App\Models\GrupoExportacao;
use App\Queries\GrupoQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class GrupoExportacaoController extends Controller
{
    use AutorizaProcessamentoUsuario;

    public function __construct(private readonly GrupoQuery $grupoQuery) {}

    public function iniciar(Request $request): JsonResponse
    {
        $filtros = $this->grupoQuery->normalizarFiltros($request->only(['search', 'per_page', 'sort', 'direction']));

        $exportacao = GrupoExportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $request->user()?->id,
            'tipo' => GrupoExportacao::TIPO_PDF,
            'status' => GrupoExportacao::STATUS_AGUARDANDO,
            'filtros' => $filtros,
        ]);

        GerarPdfGruposJob::dispatch($exportacao->id);

        return response()->json([
            'uuid' => $exportacao->uuid,
            'status' => $exportacao->status,
            'mensagem' => $this->mensagemDoStatus($exportacao),
            'created_at' => optional($exportacao->created_at)->toIso8601String(),
            'urls' => [
                'status' => route('admin.grupos.exportacoes.status', $exportacao, false),
                'download' => route('admin.grupos.exportacoes.download', $exportacao, false),
            ],
        ], 202);
    }

    public function status(Request $request, GrupoExportacao $exportacao): JsonResponse
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
                ? route('admin.grupos.exportacoes.download', $exportacao, false)
                : null,
        ]);
    }

    public function download(Request $request, GrupoExportacao $exportacao): BinaryFileResponse
    {
        $this->autorizarAcessoProcessamento($request, $exportacao);

        abort_unless($exportacao->isConcluido(), 409, 'O PDF ainda não está pronto para download.');
        abort_if($exportacao->arquivo_path === null, 404, 'Arquivo da exportação não encontrado.');
        abort_unless(Storage::disk('local')->exists($exportacao->arquivo_path), 404, 'Arquivo da exportação não encontrado.');

        return response()->download(
            Storage::disk('local')->path($exportacao->arquivo_path),
            $exportacao->arquivo_nome ?: 'grupos.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function mensagemDoStatus(GrupoExportacao $exportacao): string
    {
        return match ($exportacao->status) {
            GrupoExportacao::STATUS_AGUARDANDO => 'O PDF foi solicitado e aguarda o worker iniciar o processamento.',
            GrupoExportacao::STATUS_PROCESSANDO => 'O PDF está sendo gerado em background.',
            GrupoExportacao::STATUS_CONCLUIDO => 'O relatório foi gerado com sucesso.',
            GrupoExportacao::STATUS_FALHOU => $exportacao->erro_mensagem
                ?: 'Falha ao gerar o PDF. Tente novamente.',
            default => '',
        };
    }
}
