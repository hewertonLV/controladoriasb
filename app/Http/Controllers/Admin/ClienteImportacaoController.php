<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AutorizaProcessamentoUsuario;
use App\Http\Controllers\Controller;
use App\Jobs\Clientes\ProcessarPreviewImportacaoClientesJob;
use App\Models\Cliente;
use App\Models\ClienteHistorico;
use App\Models\ClienteImportacao;
use App\Models\Grupo;
use App\Models\Praca;
use App\Models\UnidadeNegocio;
use App\Services\Clientes\ClienteAuditoriaService;
use App\Support\TextoCadastro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ClienteImportacaoController extends Controller
{
    use AutorizaProcessamentoUsuario;

    public function __construct(
        private readonly ClienteAuditoriaService $auditoria,
    ) {}

    public function importar(): View
    {
        return view('admin.clientes.importar');
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

        $path = $file?->store('clientes/importacoes', 'local');
        if ($path === null || $path === false) {
            return response()->json([
                'message' => 'Falha ao salvar o arquivo enviado.',
            ], 500);
        }

        $importacao = ClienteImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'arquivo_original' => $original,
            'arquivo_path' => $path,
            'status' => ClienteImportacao::STATUS_AGUARDANDO,
        ]);

        ProcessarPreviewImportacaoClientesJob::dispatch($importacao->id);

        return response()->json([
            'uuid' => $importacao->uuid,
            'status' => $importacao->status,
            'urls' => [
                'status' => route('admin.clientes.importar.status', $importacao),
                'resultado' => route('admin.clientes.importar.resultado', $importacao),
                'confirmar' => route('admin.clientes.importar.confirmar', $importacao),
            ],
        ], 202);
    }

    public function status(Request $request, ClienteImportacao $importacao): JsonResponse
    {
        $this->autorizarAcessoProcessamento($request, $importacao);

        return response()->json($this->statusPayload($importacao));
    }

    public function resultado(Request $request, ClienteImportacao $importacao): JsonResponse
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

    public function confirmar(Request $request, ClienteImportacao $importacao): JsonResponse
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
                    $erroValidacao = $this->validarDadosCliente($dados);
                    if ($erroValidacao !== null) {
                        $erros[] = ['linha' => $dados['id_cigam'] ?? "novas[{$rowId}]", 'erros' => [$erroValidacao]];
                        $ignoradas++;

                        continue;
                    }

                    if (Cliente::query()->where('id_cigam', $dados['id_cigam'])->exists()) {
                        $ignoradas++;

                        continue;
                    }

                    $cliente = Cliente::create([
                        'id_cigam' => $dados['id_cigam'],
                        'razao_social' => $dados['razao_social'],
                        'fantasia' => $dados['fantasia'] ?? null,
                        'cnpj_cpf' => $dados['cnpj_cpf'],
                        'id_unidade_negocio' => (int) $dados['id_unidade_negocio'],
                        'id_praca' => (int) $dados['id_praca'],
                        'grupo_id' => $dados['grupo_id'] ?? null,
                        'desconto_nf' => $dados['desconto_nf'],
                    ]);

                    $this->auditoria->registrarCriacao(
                        $cliente,
                        $user,
                        ClienteHistorico::ORIGEM_IMPORTACAO_EXCEL,
                    );

                    $criadas++;
                }

                foreach ($rowIdsAtual as $rowId) {
                    $item = $atualIndex[$rowId] ?? null;
                    if ($item === null) {
                        $ignoradas++;

                        continue;
                    }

                    $clienteId = (int) ($item['cliente_id'] ?? 0);
                    $cliente = Cliente::query()->find($clienteId);
                    if ($cliente === null) {
                        $ignoradas++;

                        continue;
                    }

                    $dados = $item['dados_novos'] ?? [];

                    $idCigamNovo = TextoCadastro::normalizarIdCigamAteSeisDigitos((string) ($dados['id_cigam'] ?? ''));
                    if ($idCigamNovo !== $cliente->id_cigam) {
                        $erros[] = [
                            'linha' => $dados['id_cigam'] ?? "atualizacoes[{$rowId}]",
                            'erros' => ['Inconsistência: cliente_id e id_cigam não correspondem.'],
                        ];
                        $ignoradas++;

                        continue;
                    }

                    $erroValidacao = $this->validarDadosCliente($dados);
                    if ($erroValidacao !== null) {
                        $erros[] = ['linha' => $dados['id_cigam'], 'erros' => [$erroValidacao]];
                        $ignoradas++;

                        continue;
                    }

                    $antes = $this->auditoria->snapshot($cliente);

                    $cliente->update([
                        'razao_social' => $dados['razao_social'],
                        'fantasia' => $dados['fantasia'] ?? null,
                        'cnpj_cpf' => $dados['cnpj_cpf'],
                        'id_unidade_negocio' => (int) $dados['id_unidade_negocio'],
                        'id_praca' => (int) $dados['id_praca'],
                        'grupo_id' => $dados['grupo_id'] ?? null,
                        'desconto_nf' => $dados['desconto_nf'],
                    ]);

                    $depois = $this->auditoria->snapshot($cliente->fresh());

                    $this->auditoria->registrarAtualizacao(
                        $cliente,
                        $antes,
                        $depois,
                        $user,
                        ClienteHistorico::ORIGEM_IMPORTACAO_EXCEL,
                    );

                    $atualizadas++;
                }
            });
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Erro ao gravar clientes: '.$e->getMessage(),
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
    private function statusPayload(ClienteImportacao $importacao): array
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
    private function validarDadosCliente(array $dados): ?string
    {
        $doc = (string) ($dados['cnpj_cpf'] ?? '');
        if (! in_array(strlen($doc), [11, 14], true)) {
            return 'CPF/CNPJ deve ter 11 dígitos (CPF) ou 14 dígitos (CNPJ).';
        }

        if (($dados['fantasia'] ?? null) !== null && mb_strlen((string) $dados['fantasia']) > 255) {
            return 'Fantasia pode ter no máximo 255 caracteres.';
        }

        $unidadeId = (int) ($dados['id_unidade_negocio'] ?? 0);
        if ($unidadeId < 1 || ! UnidadeNegocio::query()->whereKey($unidadeId)->exists()) {
            return 'Unidade de negócio inválida ou inexistente.';
        }

        if ((float) ($dados['desconto_nf'] ?? -1) < 0) {
            return 'O desconto NF não pode ser negativo.';
        }

        $idPraca = (int) ($dados['id_praca'] ?? 0);
        if ($idPraca < 1 || ! Praca::query()->whereKey($idPraca)->exists()) {
            return 'Praça inválida ou inexistente.';
        }

        $pracaUnidade = Praca::query()
            ->whereKey($idPraca)
            ->where('id_unidade_negocio', $unidadeId)
            ->exists();
        if (! $pracaUnidade) {
            return 'A praça informada não pertence à unidade de negócio do cliente.';
        }

        $grupoId = $dados['grupo_id'] ?? null;
        if ($grupoId !== null && (int) $grupoId > 0 && ! Grupo::query()->whereKey((int) $grupoId)->exists()) {
            return 'Grupo inválido ou inexistente.';
        }

        return null;
    }

    private function removerArquivoTemporario(ClienteImportacao $importacao): void
    {
        try {
            if ($importacao->arquivo_path !== '' && Storage::disk('local')->exists($importacao->arquivo_path)) {
                Storage::disk('local')->delete($importacao->arquivo_path);
            }
        } catch (Throwable) {
        }
    }
}
