<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Roles;
use App\Http\Controllers\Controller;
use App\Jobs\Empresas\GerarPdfEmpresasJob;
use App\Models\EmpresaExportacao;
use App\Queries\EmpresaQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmpresaExportacaoController extends Controller
{
    public function __construct(private readonly EmpresaQuery $empresaQuery) {}

    public function iniciar(Request $request): JsonResponse
    {
        $filtros = $this->empresaQuery->normalizarFiltros($request->only(['search', 'per_page', 'status', 'sort', 'direction']));

        $exportacao = EmpresaExportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $request->user()?->id,
            'tipo' => EmpresaExportacao::TIPO_PDF,
            'status' => EmpresaExportacao::STATUS_AGUARDANDO,
            'filtros' => $filtros,
        ]);

        GerarPdfEmpresasJob::dispatch($exportacao->id);

        return response()->json([
            'uuid' => $exportacao->uuid,
            'status' => $exportacao->status,
            'mensagem' => $this->mensagemDoStatus($exportacao),
            'created_at' => optional($exportacao->created_at)->toIso8601String(),
            'urls' => [
                'status' => route('admin.empresas.exportacoes.status', $exportacao, false),
                'download' => route('admin.empresas.exportacoes.download', $exportacao, false),
            ],
        ], 202);
    }

    public function status(Request $request, EmpresaExportacao $exportacao): JsonResponse
    {
        $this->autorizarAcesso($request, $exportacao);

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
                ? route('admin.empresas.exportacoes.download', $exportacao, false)
                : null,
        ]);
    }

    public function download(Request $request, EmpresaExportacao $exportacao): BinaryFileResponse
    {
        $this->autorizarAcesso($request, $exportacao);

        abort_unless($exportacao->isConcluido(), 409, 'O PDF ainda não está pronto para download.');
        abort_if($exportacao->arquivo_path === null, 404, 'Arquivo da exportação não encontrado.');
        abort_unless(Storage::disk('local')->exists($exportacao->arquivo_path), 404, 'Arquivo da exportação não encontrado.');

        return response()->download(
            Storage::disk('local')->path($exportacao->arquivo_path),
            $exportacao->arquivo_nome ?: 'empresas.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function autorizarAcesso(Request $request, EmpresaExportacao $exportacao): void
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        if ($exportacao->user_id !== null && $exportacao->user_id === $user->id) {
            return;
        }

        if ($user->hasRole(Roles::PROGRAMADOR->value)) {
            return;
        }

        abort(403, 'Você não tem acesso a esta exportação.');
    }

    private function mensagemDoStatus(EmpresaExportacao $exportacao): string
    {
        return match ($exportacao->status) {
            EmpresaExportacao::STATUS_AGUARDANDO => 'O PDF foi solicitado e aguarda o worker iniciar o processamento.',
            EmpresaExportacao::STATUS_PROCESSANDO => 'O PDF está sendo gerado em background.',
            EmpresaExportacao::STATUS_CONCLUIDO => 'O relatório foi gerado com sucesso.',
            EmpresaExportacao::STATUS_FALHOU => $exportacao->erro_mensagem
                ?: 'Falha ao gerar o PDF. Tente novamente.',
            default => '',
        };
    }
}
