<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AutorizaProcessamentoUsuario;
use App\Http\Controllers\Controller;
use App\Jobs\Veiculos\ProcessarPreviewImportacaoVeiculosJob;
use App\Models\UnidadeNegocio;
use App\Models\Veiculo;
use App\Models\VeiculoHistorico;
use App\Models\VeiculoImportacao;
use App\Services\Veiculos\VeiculoAuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class VeiculoImportacaoController extends Controller
{
    use AutorizaProcessamentoUsuario;

    public function __construct(
        private readonly VeiculoAuditoriaService $auditoria,
    ) {}

    public function importar(): View
    {
        return view('admin.veiculos.importar');
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

        $path = $file?->store('veiculos/importacoes', 'local');
        if ($path === null || $path === false) {
            return response()->json([
                'message' => 'Falha ao salvar o arquivo enviado.',
            ], 500);
        }

        $importacao = VeiculoImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'arquivo_original' => $original,
            'arquivo_path' => $path,
            'status' => VeiculoImportacao::STATUS_AGUARDANDO,
        ]);

        ProcessarPreviewImportacaoVeiculosJob::dispatch($importacao->id);

        return response()->json([
            'uuid' => $importacao->uuid,
            'status' => $importacao->status,
            'urls' => [
                'status' => route('admin.veiculos.importar.status', $importacao),
                'resultado' => route('admin.veiculos.importar.resultado', $importacao),
                'confirmar' => route('admin.veiculos.importar.confirmar', $importacao),
            ],
        ], 202);
    }

    public function status(Request $request, VeiculoImportacao $importacao): JsonResponse
    {
        $this->autorizarAcessoProcessamento($request, $importacao);

        return response()->json($this->statusPayload($importacao));
    }

    public function resultado(Request $request, VeiculoImportacao $importacao): JsonResponse
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

    public function confirmar(Request $request, VeiculoImportacao $importacao): JsonResponse
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
                    $erroVal = $this->validarDados($dados);
                    if ($erroVal !== null) {
                        $erros[] = ['linha' => $dados['id_sbs'] ?? "novas[{$rowId}]", 'erros' => [$erroVal]];
                        $ignoradas++;

                        continue;
                    }

                    if (Veiculo::query()->where('id_sbs', $dados['id_sbs'])->exists()) {
                        $ignoradas++;

                        continue;
                    }

                    $veiculo = Veiculo::create([
                        'id_sbs' => $dados['id_sbs'],
                        'nome' => $dados['nome'],
                        'tipo' => $dados['tipo'],
                        'id_unidade_negocio' => $dados['id_unidade_negocio'],
                        'status' => $dados['status'],
                    ]);

                    $this->auditoria->registrarCriacao(
                        $veiculo,
                        $user,
                        VeiculoHistorico::ORIGEM_IMPORTACAO_EXCEL,
                    );

                    $criadas++;
                }

                foreach ($rowIdsAtual as $rowId) {
                    $item = $atualIndex[$rowId] ?? null;
                    if ($item === null) {
                        $ignoradas++;

                        continue;
                    }

                    $veiculoId = (int) ($item['veiculo_id'] ?? 0);
                    $veiculo = Veiculo::query()->find($veiculoId);
                    if ($veiculo === null) {
                        $ignoradas++;

                        continue;
                    }

                    $dados = $item['dados_novos'] ?? [];

                    if ((int) ($dados['id_sbs'] ?? 0) !== (int) $veiculo->id_sbs) {
                        $erros[] = [
                            'linha' => $dados['id_sbs'] ?? "atualizacoes[{$rowId}]",
                            'erros' => ['Inconsistência: veiculo_id e id_sbs não correspondem.'],
                        ];
                        $ignoradas++;

                        continue;
                    }

                    $erroVal = $this->validarDados($dados);
                    if ($erroVal !== null) {
                        $erros[] = ['linha' => $dados['id_sbs'], 'erros' => [$erroVal]];
                        $ignoradas++;

                        continue;
                    }

                    $antes = $this->auditoria->snapshot($veiculo);

                    $veiculo->update([
                        'nome' => $dados['nome'],
                        'tipo' => $dados['tipo'],
                        'id_unidade_negocio' => $dados['id_unidade_negocio'],
                        'status' => $dados['status'],
                    ]);

                    $depois = $this->auditoria->snapshot($veiculo->fresh());

                    $this->auditoria->registrarAtualizacao(
                        $veiculo,
                        $antes,
                        $depois,
                        $user,
                        VeiculoHistorico::ORIGEM_IMPORTACAO_EXCEL,
                    );

                    $atualizadas++;
                }
            });
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Erro ao gravar veículos: '.$e->getMessage(),
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
    private function statusPayload(VeiculoImportacao $importacao): array
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
    private function validarDados(array $dados): ?string
    {
        $idSbs = (int) ($dados['id_sbs'] ?? 0);
        if ($idSbs <= 0) {
            return 'ID SBS deve ser um inteiro positivo.';
        }

        $status = (string) ($dados['status'] ?? '');
        if (! in_array($status, ['ATIVO', 'INATIVO'], true)) {
            return 'Status deve ser ATIVO ou INATIVO.';
        }

        $idUnidade = (int) ($dados['id_unidade_negocio'] ?? 0);
        if ($idUnidade <= 0 || ! UnidadeNegocio::query()->whereKey($idUnidade)->exists()) {
            return 'Unidade de negócio inválida ou inexistente.';
        }

        return null;
    }

    private function removerArquivoTemporario(VeiculoImportacao $importacao): void
    {
        try {
            if ($importacao->arquivo_path !== '' && Storage::disk('local')->exists($importacao->arquivo_path)) {
                Storage::disk('local')->delete($importacao->arquivo_path);
            }
        } catch (Throwable) {
        }
    }
}
