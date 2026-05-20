<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AutorizaProcessamentoUsuario;
use App\Http\Controllers\Controller;
use App\Jobs\Frutas\ProcessarPreviewImportacaoFrutasIcmsJob;
use App\Models\Fruta;
use App\Models\FrutaIcmsHistorico;
use App\Models\FrutaIcmsImportacao;
use App\Services\Frutas\FrutaIcmsSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class FrutaIcmsImportacaoController extends Controller
{
    use AutorizaProcessamentoUsuario;

    public function __construct(
        private readonly FrutaIcmsSyncService $icmsSync,
    ) {}

    public function importar(): View
    {
        return view('admin.frutas.icms.importar');
    }

    public function iniciar(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'arquivo' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ], [
            'arquivo.required' => 'Selecione um arquivo .xlsx ou .xls.',
            'arquivo.mimes' => 'O arquivo precisa ser .xlsx ou .xls.',
            'arquivo.max' => 'O arquivo pode ter no máximo 5 MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Arquivo inválido.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $user = $request->user();
        $file = $request->file('arquivo');
        $path = $file?->store('frutas/icms-importacoes', 'local');

        if ($path === null || $path === false) {
            return response()->json(['message' => 'Falha ao salvar o arquivo enviado.'], 500);
        }

        $importacao = FrutaIcmsImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'arquivo_original' => $file?->getClientOriginalName(),
            'arquivo_path' => $path,
            'status' => FrutaIcmsImportacao::STATUS_AGUARDANDO,
        ]);

        ProcessarPreviewImportacaoFrutasIcmsJob::dispatch($importacao->id);

        return response()->json([
            'uuid' => $importacao->uuid,
            'status' => $importacao->status,
            'urls' => [
                'status' => route('admin.frutas.icms.importar.status', $importacao),
                'resultado' => route('admin.frutas.icms.importar.resultado', $importacao),
                'confirmar' => route('admin.frutas.icms.importar.confirmar', $importacao),
            ],
        ], 202);
    }

    public function status(Request $request, FrutaIcmsImportacao $importacao): JsonResponse
    {
        $this->autorizarAcessoProcessamento($request, $importacao);

        return response()->json([
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
        ]);
    }

    public function resultado(Request $request, FrutaIcmsImportacao $importacao): JsonResponse
    {
        $this->autorizarAcessoProcessamento($request, $importacao);

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

    public function confirmar(Request $request, FrutaIcmsImportacao $importacao): JsonResponse
    {
        $this->autorizarAcessoProcessamento($request, $importacao);

        if (! $importacao->isConcluido()) {
            return response()->json([
                'message' => 'A análise da importação ainda não terminou.',
                'status' => $importacao->status,
            ], 409);
        }

        $payload = $request->validate([
            'row_ids_novas' => ['nullable', 'array'],
            'row_ids_novas.*' => ['integer', 'min:1'],
            'row_ids_atualizacoes' => ['nullable', 'array'],
            'row_ids_atualizacoes.*' => ['integer', 'min:1'],
        ]);

        $rowIds = array_values(array_unique(array_merge(
            array_map('intval', $payload['row_ids_novas'] ?? []),
            array_map('intval', $payload['row_ids_atualizacoes'] ?? []),
        )));

        if ($rowIds === []) {
            return response()->json(['message' => 'Nenhum item foi selecionado para importação.'], 422);
        }

        $resultado = $importacao->resultado ?? [];
        $index = $this->indexarPorRowId(array_merge(
            $resultado['novas'] ?? [],
            $resultado['atualizacoes'] ?? [],
        ));

        $aplicadas = 0;
        $ignoradas = 0;
        $erros = [];

        try {
            DB::transaction(function () use ($rowIds, $index, &$aplicadas, &$ignoradas, &$erros, $request): void {
                foreach ($rowIds as $rowId) {
                    $item = $index[$rowId] ?? null;
                    if ($item === null) {
                        $ignoradas++;

                        continue;
                    }

                    $fruta = Fruta::query()->find((int) ($item['fruta_id'] ?? 0));
                    $idEstado = (int) ($item['id_estado'] ?? 0);
                    $dados = $item['dados_novos'] ?? [];

                    if ($fruta === null || $idEstado <= 0) {
                        $ignoradas++;

                        continue;
                    }

                    try {
                        $this->icmsSync->syncEstado(
                            $fruta,
                            $idEstado,
                            $dados,
                            $request->user(),
                            FrutaIcmsHistorico::ORIGEM_IMPORTACAO,
                        );
                        $aplicadas++;
                    } catch (Throwable $e) {
                        $erros[] = [
                            'linha' => $item['linha'] ?? $rowId,
                            'erros' => [$e->getMessage()],
                        ];
                    }
                }
            });
        } catch (Throwable $e) {
            return response()->json(['message' => 'Falha ao confirmar importação: '.$e->getMessage()], 500);
        }

        $this->removerArquivoTemporario($importacao);

        return response()->json([
            'message' => 'Importação de ICMS concluída.',
            'resumo' => [
                'aplicadas' => $aplicadas,
                'ignoradas' => $ignoradas,
                'erros' => $erros,
            ],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $itens
     * @return array<int, array<string, mixed>>
     */
    private function indexarPorRowId(array $itens): array
    {
        $out = [];
        foreach ($itens as $item) {
            $rowId = (int) ($item['row_id'] ?? 0);
            if ($rowId > 0) {
                $out[$rowId] = $item;
            }
        }

        return $out;
    }

    private function removerArquivoTemporario(FrutaIcmsImportacao $importacao): void
    {
        try {
            if ($importacao->arquivo_path !== '' && Storage::disk('local')->exists($importacao->arquivo_path)) {
                Storage::disk('local')->delete($importacao->arquivo_path);
            }
        } catch (Throwable) {
        }
    }
}
