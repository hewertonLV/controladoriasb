<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\AutorizaProcessamentoUsuario;
use App\Http\Controllers\Controller;
use App\Jobs\UnidadesNegocio\ProcessarPreviewImportacaoUnidadesNegocioJob;
use App\Models\Estado;
use App\Models\UnidadeNegocio;
use App\Models\UnidadeNegocioHistorico;
use App\Models\UnidadeNegocioImportacao;
use App\Services\UnidadesNegocio\UnidadeNegocioAuditoriaService;
use App\Support\TextoCadastro;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class UnidadeNegocioImportacaoController extends Controller
{
    use AutorizaProcessamentoUsuario;

    public function __construct(
        private readonly UnidadeNegocioAuditoriaService $auditoria,
    ) {}

    public function importar(): View
    {
        return view('admin.unidades-negocio.importar');
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

        $path = $file?->store('unidades-negocio/importacoes', 'local');
        if ($path === null || $path === false) {
            return response()->json([
                'message' => 'Falha ao salvar o arquivo enviado.',
            ], 500);
        }

        $importacao = UnidadeNegocioImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'arquivo_original' => $original,
            'arquivo_path' => $path,
            'status' => UnidadeNegocioImportacao::STATUS_AGUARDANDO,
        ]);

        ProcessarPreviewImportacaoUnidadesNegocioJob::dispatch($importacao->id);

        return response()->json([
            'uuid' => $importacao->uuid,
            'status' => $importacao->status,
            'urls' => [
                'status' => route('admin.unidades-negocio.importar.status', $importacao),
                'resultado' => route('admin.unidades-negocio.importar.resultado', $importacao),
                'confirmar' => route('admin.unidades-negocio.importar.confirmar', $importacao),
            ],
        ], 202);
    }

    public function status(Request $request, UnidadeNegocioImportacao $importacao): JsonResponse
    {
        $this->autorizarAcessoProcessamento($request, $importacao);

        return response()->json($this->statusPayload($importacao));
    }

    public function resultado(Request $request, UnidadeNegocioImportacao $importacao): JsonResponse
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

    public function confirmar(Request $request, UnidadeNegocioImportacao $importacao): JsonResponse
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
                    $erroVal = $this->validarDadosLinha($dados);
                    if ($erroVal !== null) {
                        $erros[] = ['linha' => ($dados['id_cigam'] ?? '') !== '' ? $dados['id_cigam'] : "novas[{$rowId}]", 'erros' => [$erroVal]];
                        $ignoradas++;

                        continue;
                    }

                    if (UnidadeNegocio::query()->where('id_cigam', $dados['id_cigam'])->exists()) {
                        $ignoradas++;

                        continue;
                    }

                    $unidade = UnidadeNegocio::create([
                        'id_cigam' => $dados['id_cigam'],
                        'id_estado' => (int) $dados['id_estado'],
                        'razao_social' => $dados['razao_social'],
                        'nome' => $dados['nome'],
                        'cpf_cnpj' => $dados['cpf_cnpj'],
                        'custo_operacional' => $dados['custo_operacional'],
                        'possui_estoque' => (bool) ($dados['possui_estoque'] ?? false),
                        'is_unidade_producao' => (bool) ($dados['is_unidade_producao'] ?? false),
                        'is_hub' => (bool) ($dados['is_hub'] ?? false),
                        'is_galpao_operacional' => (bool) ($dados['is_galpao_operacional'] ?? false),
                        'emite_nota_fiscal' => (bool) ($dados['emite_nota_fiscal'] ?? false),
                        'status' => true,
                    ]);

                    $this->auditoria->registrarCriacao(
                        $unidade,
                        $user,
                        UnidadeNegocioHistorico::ORIGEM_IMPORTACAO_EXCEL,
                    );

                    $criadas++;
                }

                foreach ($rowIdsAtual as $rowId) {
                    $item = $atualIndex[$rowId] ?? null;
                    if ($item === null) {
                        $ignoradas++;

                        continue;
                    }

                    $unidadeId = (int) ($item['unidade_negocio_id'] ?? 0);
                    $unidade = UnidadeNegocio::query()->find($unidadeId);
                    if ($unidade === null) {
                        $ignoradas++;

                        continue;
                    }

                    $dados = $item['dados_novos'] ?? [];
                    if (($dados['id_cigam'] ?? null) !== $unidade->id_cigam) {
                        $erros[] = [
                            'linha' => $dados['id_cigam'] ?? "atualizacoes[{$rowId}]",
                            'erros' => ['Inconsistência: unidade_negocio_id e id_cigam não correspondem.'],
                        ];
                        $ignoradas++;

                        continue;
                    }

                    $erroVal = $this->validarDadosLinha($dados);
                    if ($erroVal !== null) {
                        $erros[] = ['linha' => $unidade->id_cigam, 'erros' => [$erroVal]];
                        $ignoradas++;

                        continue;
                    }

                    $antes = $this->auditoria->snapshot($unidade);

                    $unidade->update([
                        'razao_social' => $dados['razao_social'],
                        'nome' => $dados['nome'],
                        'cpf_cnpj' => $dados['cpf_cnpj'],
                        'custo_operacional' => $dados['custo_operacional'],
                        'possui_estoque' => (bool) ($dados['possui_estoque'] ?? false),
                        'is_unidade_producao' => (bool) ($dados['is_unidade_producao'] ?? false),
                        'is_hub' => (bool) ($dados['is_hub'] ?? false),
                        'is_galpao_operacional' => (bool) ($dados['is_galpao_operacional'] ?? false),
                        'emite_nota_fiscal' => (bool) ($dados['emite_nota_fiscal'] ?? false),
                        'id_estado' => (int) $dados['id_estado'],
                    ]);

                    $depois = $this->auditoria->snapshot($unidade->fresh());

                    $this->auditoria->registrarAtualizacao(
                        $unidade,
                        $antes,
                        $depois,
                        $user,
                        UnidadeNegocioHistorico::ORIGEM_IMPORTACAO_EXCEL,
                    );

                    $atualizadas++;
                }
            });
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Erro ao gravar unidades de negócio: '.$e->getMessage(),
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
    private function statusPayload(UnidadeNegocioImportacao $importacao): array
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
    private function validarDadosLinha(array $dados): ?string
    {
        $idCigam = TextoCadastro::normalizarIdCigamAteSeisDigitos((string) ($dados['id_cigam'] ?? ''));
        $nome = TextoCadastro::normalizarMaiusculas((string) ($dados['nome'] ?? ''));
        $razaoSocial = TextoCadastro::normalizarMaiusculas((string) ($dados['razao_social'] ?? ''));
        $cpfCnpj = TextoCadastro::somenteDigitos((string) ($dados['cpf_cnpj'] ?? ''));

        if ($idCigam === '') {
            return 'ID CIGAM é obrigatório.';
        }
        if (! preg_match('/^\d{6}$/', $idCigam)) {
            return 'ID CIGAM deve ter no máximo 6 dígitos numéricos.';
        }
        if ($razaoSocial === '') {
            return 'Razão social é obrigatória.';
        }
        if ($nome === '') {
            return 'Nome é obrigatório.';
        }
        if ($cpfCnpj !== '' && ! in_array(strlen($cpfCnpj), [11, 14], true)) {
            return 'CPF/CNPJ deve ter 11 dígitos (CPF) ou 14 dígitos (CNPJ).';
        }
        foreach ([
            'possui_estoque',
            'is_unidade_producao',
            'is_hub',
            'is_galpao_operacional',
            'emite_nota_fiscal',
        ] as $flag) {
            if (! array_key_exists($flag, $dados)) {
                return 'Dados da planilha incompletos (flags de unidade). Reprocesse a importação.';
            }
        }

        if (($dados['is_galpao_operacional'] ?? false) && ! ($dados['possui_estoque'] ?? false)) {
            return 'Galpão operacional deve controlar estoque de frutas.';
        }

        if (($dados['is_hub'] ?? false) && ($dados['emite_nota_fiscal'] ?? false)) {
            return 'Unidade HUB não emite nota fiscal.';
        }

        $idEstado = (int) ($dados['id_estado'] ?? 0);
        if ($idEstado < 1) {
            return 'Estado (ICMS) é obrigatório.';
        }
        if (! Estado::query()->whereKey($idEstado)->exists()) {
            return 'Estado (ICMS) inválido.';
        }

        return null;
    }

    private function removerArquivoTemporario(UnidadeNegocioImportacao $importacao): void
    {
        try {
            if ($importacao->arquivo_path !== '' && Storage::disk('local')->exists($importacao->arquivo_path)) {
                Storage::disk('local')->delete($importacao->arquivo_path);
            }
        } catch (Throwable) {
            // best-effort
        }
    }
}
