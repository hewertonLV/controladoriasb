<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Roles;
use App\Http\Controllers\Controller;
use App\Models\EmpresaImportacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Fluxo legado de planilha exclusivo de "empresas" foi descontinuado.
 * O hub corporativo passa a refletir automaticamente clientes, fornecedores e unidades.
 */
class EmpresaImportacaoController extends Controller
{
    public function importar(): View
    {
        return view('admin.empresas.importar');
    }

    public function iniciar(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Esta importação foi descontinuada. Utilize as planilhas de Clientes, Fornecedores ou Unidades de negócio: o registro correspondente no hub corporativo é criado automaticamente.',
        ], 410);
    }

    public function status(Request $request, EmpresaImportacao $importacao): JsonResponse
    {
        $this->autorizarAcesso($request, $importacao);

        return response()->json($this->statusPayload($importacao));
    }

    public function resultado(Request $request, EmpresaImportacao $importacao): JsonResponse
    {
        $this->autorizarAcesso($request, $importacao);

        if (! $importacao->isConcluido()) {
            return response()->json([
                'message' => 'A importação ainda não foi concluída.',
                'status' => $importacao->status,
            ], 409);
        }

        $resultado = $importacao->resultado ?? [];

        return response()->json([
            'status' => $importacao->status,
            'novas' => $resultado['novas'] ?? [],
            'atualizacoes' => $resultado['atualizacoes'] ?? [],
            'sem_alteracoes' => $resultado['sem_alteracoes'] ?? [],
            'erros' => $resultado['erros'] ?? [],
        ]);
    }

    public function confirmar(Request $request, EmpresaImportacao $importacao): JsonResponse
    {
        $this->autorizarAcesso($request, $importacao);

        return response()->json([
            'message' => 'Confirmação descontinuada. Use as importações dos módulos de cadastro (Clientes, Fornecedores, Unidades de negócio).',
        ], 410);
    }

    /**
     * @return array<string, mixed>
     */
    private function statusPayload(EmpresaImportacao $importacao): array
    {
        return [
            'uuid' => $importacao->uuid,
            'status' => $importacao->status,
            'total_linhas' => $importacao->total_linhas,
            'linhas_processadas' => $importacao->linhas_processadas,
            'percentual' => $importacao->percentual,
            'novas_count' => $importacao->novas_count,
            'atualizacoes_count' => $importacao->atualizacoes_count,
            'sem_alteracoes_count' => $importacao->sem_alteracoes_count,
            'erros_count' => $importacao->erros_count,
            'arquivo_original' => $importacao->arquivo_original,
            'erro_mensagem' => $importacao->erro_mensagem,
            'started_at' => optional($importacao->started_at)->toIso8601String(),
            'finished_at' => optional($importacao->finished_at)->toIso8601String(),
        ];
    }

    private function autorizarAcesso(Request $request, EmpresaImportacao $importacao): void
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        if ($importacao->user_id !== null && $importacao->user_id === $user->id) {
            return;
        }

        if ($user->hasRole(Roles::PROGRAMADOR->value)) {
            return;
        }

        abort(403, 'Você não tem acesso a esta importação.');
    }
}
