<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AutorizaProcessamentoUsuario;
use App\Http\Controllers\Controller;
use App\Jobs\Estados\ProcessarPreviewImportacaoEstadosJob;
use App\Models\Estado;
use App\Models\EstadoImportacao;
use App\Services\Estados\EstadoImportacaoProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class EstadoImportacaoController extends Controller
{
    use AutorizaProcessamentoUsuario;

    public function importar(): View
    {
        return view('admin.estados.importar');
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
        $original = $file?->getClientOriginalName();

        $path = $file?->store('estados/importacoes', 'local');
        if ($path === null || $path === false) {
            return response()->json([
                'message' => 'Falha ao salvar o arquivo enviado.',
            ], 500);
        }

        $importacao = EstadoImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'arquivo_original' => $original,
            'arquivo_path' => $path,
            'status' => EstadoImportacao::STATUS_AGUARDANDO,
        ]);

        ProcessarPreviewImportacaoEstadosJob::dispatch($importacao->id);

        return response()->json([
            'uuid' => $importacao->uuid,
            'status' => $importacao->status,
            'urls' => [
                'status' => route('admin.estados.importar.status', $importacao),
                'resultado' => route('admin.estados.importar.resultado', $importacao),
                'confirmar' => route('admin.estados.importar.confirmar', $importacao),
            ],
        ], 202);
    }

    public function status(Request $request, EstadoImportacao $importacao): JsonResponse
    {
        $this->autorizarAcessoProcessamento($request, $importacao);

        return response()->json($this->statusPayload($importacao));
    }

    public function resultado(Request $request, EstadoImportacao $importacao): JsonResponse
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

    public function confirmar(Request $request, EstadoImportacao $importacao): JsonResponse
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

        $rowIdsNovas = array_values(array_unique(array_map('intval', $payload['row_ids_novas'] ?? [])));
        $rowIdsAtual = array_values(array_unique(array_map('intval', $payload['row_ids_atualizacoes'] ?? [])));

        if ($rowIdsNovas === [] && $rowIdsAtual === []) {
            return response()->json([
                'message' => 'Nenhum item foi selecionado para importação.',
            ], 422);
        }

        @set_time_limit(900);

        $resultado = $importacao->resultado ?? [];
        $novasIndex = $this->indexarPorRowId($resultado['novas'] ?? []);
        $atualIndex = $this->indexarPorRowId($resultado['atualizacoes'] ?? []);

        $criadas = 0;
        $atualizadas = 0;
        $ignoradas = 0;
        $erros = [];

        try {
            DB::transaction(function () use (
                $rowIdsNovas,
                $rowIdsAtual,
                $novasIndex,
                $atualIndex,
                &$criadas,
                &$atualizadas,
                &$ignoradas,
                &$erros,
            ): void {
                foreach ($rowIdsNovas as $rowId) {
                    $item = $novasIndex[$rowId] ?? null;
                    if ($item === null) {
                        $ignoradas++;

                        continue;
                    }

                    $dados = $item['dados'] ?? [];
                    $idCigam = (string) ($dados['id_cigam'] ?? '');
                    $abreviacao = (string) ($dados['abreviacao'] ?? '');

                    if ($idCigam === '' || $abreviacao === '') {
                        $ignoradas++;

                        continue;
                    }

                    if (Estado::withTrashed()->where('id_cigam', $idCigam)->orWhere('abreviacao', $abreviacao)->exists()) {
                        $ignoradas++;

                        continue;
                    }

                    Estado::query()->create([
                        'id_cigam' => $idCigam,
                        'nome' => $dados['nome'],
                        'abreviacao' => $dados['abreviacao'],
                        'descricao' => $dados['descricao'] ?? null,
                    ]);

                    $criadas++;
                }

                foreach ($rowIdsAtual as $rowId) {
                    $item = $atualIndex[$rowId] ?? null;
                    if ($item === null) {
                        $ignoradas++;

                        continue;
                    }

                    $estadoId = (int) ($item['estado_id'] ?? 0);
                    $estado = Estado::withTrashed()->find($estadoId);
                    if ($estado === null) {
                        $ignoradas++;

                        continue;
                    }

                    $dados = $item['dados_novos'] ?? [];
                    $idCigamChave = (string) ($item['id_cigam'] ?? $dados['id_cigam'] ?? '');

                    if ($idCigamChave !== $estado->id_cigam) {
                        $erros[] = [
                            'linha' => $idCigamChave !== '' ? $idCigamChave : "atualizacoes[{$rowId}]",
                            'erros' => ['Inconsistência: estado_id e ID CIGAM não correspondem.'],
                        ];
                        $ignoradas++;

                        continue;
                    }

                    if ($estado->trashed()) {
                        $estado->restore();
                    }

                    $estado->update([
                        'id_cigam' => $dados['id_cigam'],
                        'nome' => $dados['nome'],
                        'abreviacao' => $dados['abreviacao'],
                        'descricao' => $dados['descricao'] ?? null,
                    ]);

                    $atualizadas++;
                }
            });
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Erro ao gravar estados: '.$e->getMessage(),
            ], 500);
        }

        $this->removerArquivoTemporario($importacao);

        return response()->json([
            'message' => 'Importação concluída.',
            'resumo' => [
                'criadas' => $criadas,
                'atualizadas' => $atualizadas,
                'ignoradas' => $ignoradas,
                'erros' => $erros,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function statusPayload(EstadoImportacao $importacao): array
    {
        $workerStatus = $this->workerStatus();

        return [
            'uuid' => $importacao->uuid,
            'status' => $importacao->status,
            'mensagem' => $this->mensagemStatus($importacao, $workerStatus),
            'worker_status' => $workerStatus,
            'fila_nome' => 'estados-importacao',
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

    /**
     * @param  list<array<string,mixed>>  $itens
     * @return array<int, array<string,mixed>>
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

    private function mensagemStatus(EstadoImportacao $importacao, string $workerStatus): string
    {
        return match ($importacao->status) {
            EstadoImportacao::STATUS_AGUARDANDO => $workerStatus === 'INATIVO'
                ? 'Não foi detectado worker ativo para a fila estados-importacao. Reinicie o container worker-importacao (docker compose up -d worker-importacao).'
                : 'Seu arquivo é o próximo da fila de importação de estados.',
            EstadoImportacao::STATUS_PROCESSANDO => $importacao->total_linhas > 0
                ? "Processados {$importacao->linhas_processadas} de {$importacao->total_linhas} registros."
                : 'Processando planilha de estados.',
            EstadoImportacao::STATUS_CONCLUIDO => 'Análise da importação concluída.',
            EstadoImportacao::STATUS_FALHOU => $importacao->erro_mensagem ?: 'O processamento falhou.',
            default => '',
        };
    }

    private function workerStatus(): string
    {
        $lastSeen = Cache::get(EstadoImportacaoProcessor::HEARTBEAT_CACHE_KEY);

        if (! is_string($lastSeen) || $lastSeen === '') {
            return 'INATIVO';
        }

        try {
            return now()->diffInSeconds(Carbon::parse($lastSeen), true) <= 120
                ? 'ATIVO'
                : 'INATIVO';
        } catch (Throwable) {
            return 'INATIVO';
        }
    }

    private function removerArquivoTemporario(EstadoImportacao $importacao): void
    {
        try {
            if ($importacao->arquivo_path !== '' && Storage::disk('local')->exists($importacao->arquivo_path)) {
                Storage::disk('local')->delete($importacao->arquivo_path);
            }
        } catch (Throwable) {
        }
    }
}
