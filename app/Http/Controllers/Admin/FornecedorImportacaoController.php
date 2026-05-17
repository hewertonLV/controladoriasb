<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AutorizaProcessamentoUsuario;
use App\Http\Controllers\Controller;
use App\Jobs\Fornecedores\ProcessarPreviewImportacaoFornecedoresJob;
use App\Models\Estado;
use App\Models\Fornecedor;
use App\Models\FornecedorHistorico;
use App\Models\FornecedorImportacao;
use App\Services\Fornecedores\FornecedorAuditoriaService;
use App\Services\Fornecedores\FornecedorImportacaoProcessor;
use App\Support\TextoCadastro;
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

class FornecedorImportacaoController extends Controller
{
    use AutorizaProcessamentoUsuario;

    public function __construct(
        private readonly FornecedorAuditoriaService $auditoria,
    ) {}

    public function importar(): View
    {
        return view('admin.fornecedores.importar');
    }

    public function iniciar(Request $request): JsonResponse
    {
        if (app()->environment('production') && config('queue.default') === 'sync') {
            return response()->json([
                'message' => 'Importações de fornecedores exigem QUEUE_CONNECTION=database ou redis em produção.',
            ], 500);
        }

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

        $path = $file?->store('fornecedores/importacoes', 'local');
        if ($path === null || $path === false) {
            return response()->json([
                'message' => 'Falha ao salvar o arquivo enviado.',
            ], 500);
        }

        $importacao = FornecedorImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'arquivo_original' => $original,
            'arquivo_path' => $path,
            'status' => FornecedorImportacao::STATUS_AGUARDANDO,
        ]);

        ProcessarPreviewImportacaoFornecedoresJob::dispatch($importacao->id);

        return response()->json([
            'uuid' => $importacao->uuid,
            'status' => $importacao->status,
            'urls' => [
                'status' => route('admin.fornecedores.importar.status', $importacao),
                'resultado' => route('admin.fornecedores.importar.resultado', $importacao),
                'confirmar' => route('admin.fornecedores.importar.confirmar', $importacao),
            ],
        ], 202);
    }

    public function status(Request $request, FornecedorImportacao $importacao): JsonResponse
    {
        $this->autorizarAcessoProcessamento($request, $importacao);

        return response()->json($this->statusPayload($importacao));
    }

    public function resultado(Request $request, FornecedorImportacao $importacao): JsonResponse
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

    public function confirmar(Request $request, FornecedorImportacao $importacao): JsonResponse
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

        $user = $request->user();

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
                $user,
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
                    $erroDoc = $this->validarDocumento($dados);
                    if ($erroDoc !== null) {
                        $erros[] = ['linha' => $dados['id_cigam'] ?? "novas[{$rowId}]", 'erros' => [$erroDoc]];
                        $ignoradas++;

                        continue;
                    }

                    $erroEstado = $this->validarIdEstado($dados);
                    if ($erroEstado !== null) {
                        $erros[] = ['linha' => $dados['id_cigam'] ?? "novas[{$rowId}]", 'erros' => [$erroEstado]];
                        $ignoradas++;

                        continue;
                    }

                    if (Fornecedor::query()->where('id_cigam', $dados['id_cigam'])->exists()) {
                        $ignoradas++;

                        continue;
                    }

                    if (Fornecedor::query()->where('cnpj_cpf', $dados['cnpj_cpf'])->exists()) {
                        $erros[] = [
                            'linha' => $dados['id_cigam'],
                            'erros' => ['CPF/CNPJ já cadastrado em outro fornecedor.'],
                        ];
                        $ignoradas++;

                        continue;
                    }

                    $fornecedor = Fornecedor::create([
                        'id_cigam' => $dados['id_cigam'],
                        'id_estado' => (int) $dados['id_estado'],
                        'razao_social' => $dados['razao_social'],
                        'fantasia' => $dados['fantasia'] ?? null,
                        'cnpj_cpf' => $dados['cnpj_cpf'],
                    ]);

                    $this->auditoria->registrarCriacao(
                        $fornecedor,
                        $user,
                        FornecedorHistorico::ORIGEM_IMPORTACAO_EXCEL,
                    );

                    $criadas++;
                }

                foreach ($rowIdsAtual as $rowId) {
                    $item = $atualIndex[$rowId] ?? null;
                    if ($item === null) {
                        $ignoradas++;

                        continue;
                    }

                    $fornecedorId = (int) ($item['fornecedor_id'] ?? 0);
                    $fornecedor = Fornecedor::query()->find($fornecedorId);
                    if ($fornecedor === null) {
                        $ignoradas++;

                        continue;
                    }

                    $dados = $item['dados_novos'] ?? [];

                    $idCigamNovo = TextoCadastro::normalizarIdCigamAteSeisDigitos((string) ($dados['id_cigam'] ?? ''));
                    if ($idCigamNovo !== $fornecedor->id_cigam) {
                        $erros[] = [
                            'linha' => $dados['id_cigam'] ?? "atualizacoes[{$rowId}]",
                            'erros' => ['Inconsistência: fornecedor_id e id_cigam não correspondem.'],
                        ];
                        $ignoradas++;

                        continue;
                    }

                    $erroDoc = $this->validarDocumento($dados);
                    if ($erroDoc !== null) {
                        $erros[] = ['linha' => $dados['id_cigam'], 'erros' => [$erroDoc]];
                        $ignoradas++;

                        continue;
                    }

                    $colisao = Fornecedor::query()
                        ->where('cnpj_cpf', $dados['cnpj_cpf'])
                        ->where('id', '!=', $fornecedor->id)
                        ->exists();

                    if ($colisao) {
                        $erros[] = [
                            'linha' => $dados['id_cigam'],
                            'erros' => ['CPF/CNPJ já cadastrado em outro fornecedor.'],
                        ];
                        $ignoradas++;

                        continue;
                    }

                    $erroEstado = $this->validarIdEstado($dados);
                    if ($erroEstado !== null) {
                        $erros[] = ['linha' => $dados['id_cigam'], 'erros' => [$erroEstado]];
                        $ignoradas++;

                        continue;
                    }

                    $antes = $this->auditoria->snapshot($fornecedor);

                    $fornecedor->update([
                        'id_estado' => (int) $dados['id_estado'],
                        'razao_social' => $dados['razao_social'],
                        'fantasia' => $dados['fantasia'] ?? null,
                        'cnpj_cpf' => $dados['cnpj_cpf'],
                    ]);

                    $depois = $this->auditoria->snapshot($fornecedor->fresh());

                    $this->auditoria->registrarAtualizacao(
                        $fornecedor,
                        $antes,
                        $depois,
                        $user,
                        FornecedorHistorico::ORIGEM_IMPORTACAO_EXCEL,
                    );

                    $atualizadas++;
                }
            });
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Erro ao gravar fornecedores: '.$e->getMessage(),
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
    private function statusPayload(FornecedorImportacao $importacao): array
    {
        $resultado = $importacao->resultado ?? [];
        $erros = $resultado['erros'] ?? [];
        $fila = $this->resumoFila($importacao);
        $workerStatus = $this->workerStatus();

        return [
            'importacao_id' => $importacao->id,
            'uuid' => $importacao->uuid,
            'status' => $importacao->status,
            'progresso' => $importacao->percentual,
            'mensagem' => $this->mensagemStatus($importacao, $fila['arquivos_na_frente'], $workerStatus),
            'total_linhas' => $importacao->total_linhas,
            'linhas_processadas' => $importacao->linhas_processadas,
            'percentual' => $importacao->percentual,
            'novas_count' => $importacao->novas_count,
            'atualizacoes_count' => $importacao->atualizacoes_count,
            'sem_alteracoes_count' => $importacao->sem_alteracoes_count,
            'erros_count' => $importacao->erros_count,
            'erros' => $erros,
            'arquivo_original' => $importacao->arquivo_original,
            'usuario_nome' => $importacao->user?->name ?? '—',
            'posicao_fila' => $fila['posicao_fila'],
            'arquivos_na_frente' => $fila['arquivos_na_frente'],
            'total_aguardando' => $fila['total_aguardando'],
            'total_processando' => $fila['total_processando'],
            'fila_nome' => 'imports',
            'worker_status' => $workerStatus,
            'estimativa_inicio_texto' => $fila['estimativa_inicio_texto'],
            'processando_agora' => $fila['processando_agora'],
            'erro_mensagem' => $importacao->erro_mensagem,
            'started_at' => optional($importacao->started_at)->toIso8601String(),
            'created_at' => optional($importacao->created_at)->toIso8601String(),
            'finished_at' => optional($importacao->finished_at)->toIso8601String(),
        ];
    }

    private function mensagemStatus(FornecedorImportacao $importacao, int $arquivosNaFrente, string $workerStatus): string
    {
        return match ($importacao->status) {
            FornecedorImportacao::STATUS_AGUARDANDO => $workerStatus === 'INATIVO'
                ? 'Não foi detectado worker ativo para a fila de importações. Verifique o queue:work ou Supervisor.'
                : ($arquivosNaFrente > 0
                    ? 'Seu arquivo será iniciado após os arquivos anteriores terminarem.'
                    : 'Seu arquivo é o próximo da fila de importações.'),
            FornecedorImportacao::STATUS_PROCESSANDO => $importacao->total_linhas > 0
                ? "Processados {$importacao->linhas_processadas} de {$importacao->total_linhas} registros."
                : 'Processando planilha de fornecedores.',
            FornecedorImportacao::STATUS_CONCLUIDO => 'Análise da importação concluída.',
            FornecedorImportacao::STATUS_FALHOU => $importacao->erro_mensagem ?: 'O processamento falhou.',
            default => '',
        };
    }

    /**
     * @return array{
     *     posicao_fila:int|null,
     *     arquivos_na_frente:int,
     *     total_aguardando:int,
     *     total_processando:int,
     *     estimativa_inicio_texto:string|null,
     *     processando_agora:list<array<string,mixed>>
     * }
     */
    private function resumoFila(FornecedorImportacao $importacao): array
    {
        $statusAguardando = [FornecedorImportacao::STATUS_AGUARDANDO];
        $statusNaoFinalizados = [
            FornecedorImportacao::STATUS_AGUARDANDO,
            FornecedorImportacao::STATUS_PROCESSANDO,
        ];

        $arquivosNaFrente = 0;
        if ($importacao->isAguardando()) {
            $arquivosNaFrente = FornecedorImportacao::query()
                ->whereIn('status', $statusNaoFinalizados)
                ->where(function ($query) use ($importacao): void {
                    $query->where('created_at', '<', $importacao->created_at)
                        ->orWhere(function ($q) use ($importacao): void {
                            $q->where('created_at', $importacao->created_at)
                                ->where('id', '<', $importacao->id);
                        });
                })
                ->count();
        }

        $processandoAgora = FornecedorImportacao::query()
            ->with('user:id,name')
            ->where('status', FornecedorImportacao::STATUS_PROCESSANDO)
            ->orderBy('started_at')
            ->orderBy('id')
            ->limit(10)
            ->get(['id', 'uuid', 'user_id', 'arquivo_original', 'status', 'percentual', 'linhas_processadas', 'total_linhas', 'started_at'])
            ->map(fn (FornecedorImportacao $item): array => [
                'id' => $item->id,
                'uuid' => $item->uuid,
                'tipo' => 'fornecedores',
                'arquivo_original' => $item->arquivo_original,
                'usuario_nome' => $item->user?->name ?? '—',
                'progresso' => $item->percentual,
                'status' => $item->status,
                'linhas_processadas' => $item->linhas_processadas,
                'total_linhas' => $item->total_linhas,
                'started_at' => optional($item->started_at)->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'posicao_fila' => $importacao->isAguardando() ? $arquivosNaFrente + 1 : null,
            'arquivos_na_frente' => $arquivosNaFrente,
            'total_aguardando' => FornecedorImportacao::query()->whereIn('status', $statusAguardando)->count(),
            'total_processando' => FornecedorImportacao::query()->where('status', FornecedorImportacao::STATUS_PROCESSANDO)->count(),
            'estimativa_inicio_texto' => $importacao->isAguardando()
                ? ($arquivosNaFrente > 0 ? 'Após '.$arquivosNaFrente.' arquivo(s) anterior(es).' : 'Próximo a iniciar quando houver worker disponível.')
                : null,
            'processando_agora' => $processandoAgora,
        ];
    }

    private function workerStatus(): string
    {
        $lastSeen = Cache::get(FornecedorImportacaoProcessor::HEARTBEAT_CACHE_KEY);

        if (! is_string($lastSeen) || $lastSeen === '') {
            return 'INATIVO';
        }

        try {
            return now()->diffInSeconds(Carbon::parse($lastSeen), true) <= 120
                ? 'ATIVO'
                : 'INATIVO';
        } catch (Throwable) {
            return 'DESCONHECIDO';
        }
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

    /**
     * @param  array<string, mixed>  $dados
     */
    private function validarDocumento(array $dados): ?string
    {
        $doc = (string) ($dados['cnpj_cpf'] ?? '');
        $len = strlen($doc);

        if (! in_array($len, [11, 14], true)) {
            return 'CPF/CNPJ deve ter 11 dígitos (CPF) ou 14 dígitos (CNPJ).';
        }

        return null;
    }

    private function validarIdEstado(array $dados): ?string
    {
        $id = (int) ($dados['id_estado'] ?? 0);

        if ($id < 1) {
            return 'Estado (ICMS) é obrigatório e deve existir na tabela de estados.';
        }

        if (! Estado::query()->whereKey($id)->exists()) {
            return 'Estado informado não existe na tabela de estados.';
        }

        return null;
    }

    private function removerArquivoTemporario(FornecedorImportacao $importacao): void
    {
        try {
            if ($importacao->arquivo_path !== '' && Storage::disk('local')->exists($importacao->arquivo_path)) {
                Storage::disk('local')->delete($importacao->arquivo_path);
            }
        } catch (Throwable) {
        }
    }
}
