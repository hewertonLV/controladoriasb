<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Http\Controllers\Admin\Concerns\AutorizaProcessamentoUsuario;
use App\Http\Controllers\Controller;
use App\Jobs\Movimentacoes\ProcessarPreviewImportacaoTransferenciasJob;
use App\Models\Empresa;
use App\Models\TransferenciaImportacao;
use App\Models\UnidadeNegocio;
use App\Services\Movimentacoes\TransferenciaMovimentacaoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use App\Support\EmpresaEntidadeQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class TransferenciaImportacaoController extends Controller
{
    use AutorizaProcessamentoUsuario;

    public function __construct(
        private readonly TransferenciaMovimentacaoService $transferencias,
    ) {}

    public function importar(): View
    {
        return view('admin.movimentacoes.transferencias.importar');
    }

    public function iniciar(Request $request): JsonResponse
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
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

        $path = $file?->store('transferencias/importacoes', 'local');
        if ($path === null || $path === false) {
            return response()->json([
                'message' => 'Falha ao salvar o arquivo enviado.',
            ], 500);
        }

        $importacao = TransferenciaImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'arquivo_original' => $original,
            'arquivo_path' => $path,
            'status' => TransferenciaImportacao::STATUS_AGUARDANDO,
        ]);

        ProcessarPreviewImportacaoTransferenciasJob::dispatch($importacao->id);

        return response()->json([
            'uuid' => $importacao->uuid,
            'status' => $importacao->status,
            'urls' => [
                'status' => route('admin.movimentacoes.transferencias.importar.status', $importacao),
                'resultado' => route('admin.movimentacoes.transferencias.importar.resultado', $importacao),
                'confirmar' => route('admin.movimentacoes.transferencias.importar.confirmar', $importacao),
            ],
        ], 202);
    }

    public function status(Request $request, TransferenciaImportacao $importacao): JsonResponse
    {
        $this->autorizarAcessoProcessamento($request, $importacao);

        return response()->json($this->statusPayload($importacao));
    }

    public function resultado(Request $request, TransferenciaImportacao $importacao): JsonResponse
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
            'atualizacoes' => [],
            'sem_alteracoes' => [],
            'erros' => $resultado['erros'] ?? [],
            'empresas_destino' => $this->empresasDestinoOpcoes($request->user()),
        ]);
    }

    public function confirmar(Request $request, TransferenciaImportacao $importacao): JsonResponse
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
            'id_empresa_destino_por_row' => ['nullable', 'array'],
            'id_empresa_destino_por_row.*' => ['integer', 'min:1'],
        ]);

        $rowIdsNovas = array_values(array_unique(array_map('intval', $payload['row_ids_novas'] ?? [])));
        $destinosPorRow = $this->normalizarDestinosPorRow($payload['id_empresa_destino_por_row'] ?? []);

        if ($rowIdsNovas === []) {
            return response()->json([
                'message' => 'Nenhuma linha foi selecionada para importação.',
            ], 422);
        }

        @set_time_limit(900);

        $resultado = $importacao->resultado ?? [];
        $novasIndex = $this->indexarPorRowId($resultado['novas'] ?? []);

        $aplicadas = 0;
        $ignoradas = 0;
        $erros = [];

        try {
            DB::transaction(function () use (
                $rowIdsNovas,
                $novasIndex,
                $destinosPorRow,
                &$aplicadas,
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
                    $idOrigem = (int) ($dados['id_empresa_origem'] ?? 0);
                    $idDestino = $destinosPorRow[$rowId] ?? (int) ($dados['id_empresa_destino'] ?? 0);

                    if ($idDestino <= 0) {
                        $erros[] = [
                            'linha' => (string) ($item['chave'] ?? $rowId),
                            'erros' => ['Destino inválido para a linha.'],
                        ];
                        $ignoradas++;

                        continue;
                    }

                    if ($idDestino === $idOrigem) {
                        $erros[] = [
                            'linha' => (string) ($item['chave'] ?? $rowId),
                            'erros' => ['Origem e destino não podem ser a mesma unidade de negócio.'],
                        ];
                        $ignoradas++;

                        continue;
                    }

                    $erroDestino = $this->validarEmpresaDestinoImportacao($idDestino);
                    if ($erroDestino !== null) {
                        $erros[] = [
                            'linha' => (string) ($item['chave'] ?? $rowId),
                            'erros' => [$erroDestino],
                        ];
                        $ignoradas++;

                        continue;
                    }

                    try {
                        $this->transferencias->criarTransferencia([
                            'id_empresa_origem' => $idOrigem,
                            'id_empresa_destino' => $idDestino,
                            'id_fruta' => (int) ($dados['id_fruta'] ?? 0),
                            'qtd_fruta_um' => (string) ($dados['qtd_fruta_um'] ?? '0'),
                            'numero_nf_origem' => (string) ($dados['numero_nf_origem'] ?? ''),
                        ]);
                        $aplicadas++;
                    } catch (InvalidArgumentException $e) {
                        $erros[] = [
                            'linha' => (string) ($item['chave'] ?? $rowId),
                            'erros' => [$e->getMessage()],
                        ];
                        $ignoradas++;
                    }
                }
            });
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Erro ao gravar transferências: '.$e->getMessage(),
            ], 500);
        }

        $this->removerArquivoTemporario($importacao);

        return response()->json([
            'message' => 'Importação concluída.',
            'resumo' => [
                'aplicadas' => $aplicadas,
                'ignoradas' => $ignoradas,
                'erros' => $erros,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function statusPayload(TransferenciaImportacao $importacao): array
    {
        return [
            'uuid' => $importacao->uuid,
            'status' => $importacao->status,
            'total_linhas' => $importacao->total_linhas,
            'linhas_processadas' => $importacao->linhas_processadas,
            'percentual' => $importacao->percentual,
            'novas_count' => $importacao->novas_count,
            'atualizacoes_count' => 0,
            'sem_alteracoes_count' => 0,
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
     * @return list<array{id: int, label: string, cnpj: string}>
     */
    private function empresasDestinoOpcoes(?\App\Models\User $user): array
    {
        return EmpresaEntidadeQuery::unidadesComEstoque()
            ->with('entidade')
            ->get()
            ->filter(function (Empresa $empresa) use ($user): bool {
                $unidade = $empresa->entidade;
                if (! $unidade instanceof UnidadeNegocio) {
                    return false;
                }

                if ($user === null) {
                    return true;
                }

                return app(UnidadeNegocioAccessService::class)->canAccess($user, $unidade->id);
            })
            ->sortBy(fn (Empresa $empresa): string => mb_strtolower($empresa->nomeExibicao()))
            ->map(fn (Empresa $empresa): array => [
                'id' => $empresa->id,
                'label' => $empresa->nomeExibicao(),
                'cnpj' => $empresa->entidade instanceof UnidadeNegocio
                    ? $empresa->entidade->cpf_cnpj
                    : '',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int|string, mixed>  $raw
     * @return array<int, int>
     */
    private function normalizarDestinosPorRow(array $raw): array
    {
        $out = [];
        foreach ($raw as $rowId => $empresaId) {
            $rowIdInt = (int) $rowId;
            if ($rowIdInt > 0) {
                $out[$rowIdInt] = (int) $empresaId;
            }
        }

        return $out;
    }

    private function validarEmpresaDestinoImportacao(int $idEmpresaDestino): ?string
    {
        $empresa = Empresa::query()->with('entidade')->find($idEmpresaDestino);
        if ($empresa === null) {
            return 'Unidade de destino não encontrada.';
        }

        $unidade = $empresa->entidade;
        if (! $unidade instanceof UnidadeNegocio || ! $unidade->possui_estoque) {
            return 'Destino deve ser uma unidade de negócio que controla estoque.';
        }

        return null;
    }

    private function removerArquivoTemporario(TransferenciaImportacao $importacao): void
    {
        try {
            if ($importacao->arquivo_path !== '' && Storage::disk('local')->exists($importacao->arquivo_path)) {
                Storage::disk('local')->delete($importacao->arquivo_path);
            }
        } catch (Throwable) {
        }
    }
}
