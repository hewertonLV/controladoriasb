<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Http\Controllers\Admin\Concerns\AutorizaProcessamentoUsuario;
use App\Http\Controllers\Controller;
use App\Jobs\Movimentacoes\ProcessarPreviewImportacaoVendasJob;
use App\Models\Empresa;
use App\Models\UnidadeNegocio;
use App\Models\User;
use App\Models\VendaImportacao;
use App\Services\Movimentacoes\VendaMovimentacaoService;
use App\Services\Permissoes\UnidadeNegocioAccessService;
use App\Support\EmpresaEntidadeQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class VendaImportacaoController extends Controller
{
    use AutorizaProcessamentoUsuario;

    public function __construct(
        private readonly VendaMovimentacaoService $vendas,
    ) {}

    public function importar(): View
    {
        return view('admin.movimentacoes.vendas.importar');
    }

    public function iniciar(Request $request): JsonResponse
    {
        if (app()->environment('production') && config('queue.default') === 'sync') {
            return response()->json([
                'message' => 'Importação de vendas exige QUEUE_CONNECTION=database ou redis em produção.',
            ], 500);
        }

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

        $path = $file?->store('vendas/importacoes', 'local');
        if ($path === null || $path === false) {
            return response()->json([
                'message' => 'Falha ao salvar o arquivo enviado.',
            ], 500);
        }

        try {
            $importacao = VendaImportacao::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $user?->id,
                'arquivo_original' => $original,
                'arquivo_path' => $path,
                'status' => VendaImportacao::STATUS_AGUARDANDO,
            ]);

            ProcessarPreviewImportacaoVendasJob::dispatch($importacao->id);
        } catch (Throwable $e) {
            Log::error('Falha ao iniciar importação de vendas', [
                'arquivo' => $original,
                'user_id' => $user?->id,
                'erro' => $e->getMessage(),
            ]);

            if (Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }

            return response()->json([
                'message' => $this->mensagemErroIniciarImportacao($e),
            ], 500);
        }

        return response()->json([
            'uuid' => $importacao->uuid,
            'status' => $importacao->status,
            'urls' => [
                'status' => route('admin.movimentacoes.vendas.importar.status', $importacao),
                'resultado' => route('admin.movimentacoes.vendas.importar.resultado', $importacao),
                'confirmar' => route('admin.movimentacoes.vendas.importar.confirmar', $importacao),
            ],
        ], 202);
    }

    public function status(Request $request, VendaImportacao $importacao): JsonResponse
    {
        $this->autorizarAcessoProcessamento($request, $importacao);

        return response()->json($this->statusPayload($importacao));
    }

    public function resultado(Request $request, VendaImportacao $importacao): JsonResponse
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
            'empresas_origem' => $this->empresasOrigemOpcoes($request->user()),
            'unidades_estoque' => $this->unidadesEstoqueOpcoes($request->user()),
        ]);
    }

    public function confirmar(Request $request, VendaImportacao $importacao): JsonResponse
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
            'id_empresa_origem_por_row' => ['nullable', 'array'],
            'id_empresa_origem_por_row.*' => ['integer', 'min:1'],
            'id_unidade_negocio_estoque_por_row' => ['nullable', 'array'],
            'id_unidade_negocio_estoque_por_row.*' => ['integer', 'min:1'],
        ]);

        $rowIdsNovas = array_values(array_unique(array_map('intval', $payload['row_ids_novas'] ?? [])));
        $origensPorRow = $this->normalizarOrigensPorRow($payload['id_empresa_origem_por_row'] ?? []);
        $estoquesPorRow = $this->normalizarOrigensPorRow($payload['id_unidade_negocio_estoque_por_row'] ?? []);
        $user = $request->user();

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
                $user,
                $rowIdsNovas,
                $novasIndex,
                $origensPorRow,
                $estoquesPorRow,
                &$aplicadas,
                &$ignoradas,
                &$erros,
            ): void {
                $grupos = [];

                foreach ($rowIdsNovas as $rowId) {
                    $item = $novasIndex[$rowId] ?? null;
                    if ($item === null) {
                        $ignoradas++;

                        continue;
                    }

                    $dados = $item['dados'] ?? [];
                    $idOrigem = $origensPorRow[$rowId] ?? (int) ($dados['id_empresa_origem'] ?? 0);
                    $idDestino = (int) ($dados['id_empresa_destino'] ?? 0);
                    $idEstoque = $estoquesPorRow[$rowId] ?? null;

                    if ($idOrigem <= 0) {
                        $erros[] = [
                            'linha' => (string) ($item['chave'] ?? $rowId),
                            'erros' => ['Origem inválida para a linha.'],
                        ];
                        $ignoradas++;

                        continue;
                    }

                    $erroOrigem = $this->validarEmpresaOrigemImportacao($idOrigem, $user, $idEstoque);
                    if ($erroOrigem !== null) {
                        $erros[] = [
                            'linha' => (string) ($item['chave'] ?? $rowId),
                            'erros' => [$erroOrigem],
                        ];
                        $ignoradas++;

                        continue;
                    }

                    $dadosEfetivos = array_merge($dados, [
                        'id_empresa_origem' => $idOrigem,
                        'id_unidade_negocio_estoque' => $idEstoque,
                    ]);
                    $itemEfetivo = array_merge($item, ['dados' => $dadosEfetivos]);

                    $chaveGrupo = implode('|', [
                        (string) ($dados['numero_nf'] ?? ''),
                        $idOrigem,
                        $idDestino,
                        (string) ($idEstoque ?? ''),
                    ]);

                    $grupos[$chaveGrupo][] = $itemEfetivo;
                }

                foreach ($grupos as $itens) {
                    $primeiro = $itens[0]['dados'] ?? [];
                    $itensVenda = [];

                    foreach ($itens as $item) {
                        $d = $item['dados'] ?? [];
                        $itensVenda[] = [
                            'id_fruta' => (int) ($d['id_fruta'] ?? 0),
                            'qtd_fruta_um' => (string) ($d['qtd_fruta_um'] ?? '0'),
                            'valor_nf_total' => (string) ($d['valor_nf_total'] ?? '0'),
                        ];
                    }

                    $payloadVenda = [
                        'numero_nf' => (string) ($primeiro['numero_nf'] ?? ''),
                        'id_empresa_origem' => (int) ($primeiro['id_empresa_origem'] ?? 0),
                        'id_empresa_destino' => (int) ($primeiro['id_empresa_destino'] ?? 0),
                        'itens' => $itensVenda,
                    ];

                    if (($primeiro['id_unidade_negocio_estoque'] ?? null) !== null) {
                        $payloadVenda['id_unidade_negocio_estoque'] = (int) $primeiro['id_unidade_negocio_estoque'];
                    }

                    try {
                        $resultadoVenda = $this->vendas->registrarVenda($payloadVenda, $user);

                        $aplicadas += $resultadoVenda['movimentacoes']->count();
                    } catch (InvalidArgumentException $e) {
                        foreach ($itens as $item) {
                            $erros[] = [
                                'linha' => (string) ($item['chave'] ?? $item['row_id'] ?? ''),
                                'erros' => [$e->getMessage()],
                            ];
                            $ignoradas++;
                        }
                    }
                }
            });
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Erro ao gravar vendas: '.$e->getMessage(),
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
    private function statusPayload(VendaImportacao $importacao): array
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
     * @return list<array{id: int, label: string, is_hub: bool}>
     */
    private function empresasOrigemOpcoes(?User $user): array
    {
        return EmpresaEntidadeQuery::unidadesComEstoque()
            ->with('entidade')
            ->get()
            ->filter(function (Empresa $empresa) use ($user): bool {
                $unidade = $empresa->entidade;
                if (! $unidade instanceof UnidadeNegocio) {
                    return false;
                }

                if ($unidade->is_hub) {
                    return false;
                }

                if ($user === null) {
                    return true;
                }

                return app(UnidadeNegocioAccessService::class)->canVenda($user, $unidade->id);
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
     * @return list<array{id: int, label: string, is_hub: bool}>
     */
    private function unidadesEstoqueOpcoes(?User $user): array
    {
        return UnidadeNegocio::query()
            ->where('possui_estoque', true)
            ->permitidasPara($user)
            ->orderBy('nome')
            ->get(['id', 'nome', 'razao_social', 'is_hub'])
            ->map(fn (UnidadeNegocio $unidade): array => [
                'id' => $unidade->id,
                'label' => $unidade->nome ?: $unidade->razao_social,
                'is_hub' => (bool) $unidade->is_hub,
            ])
            ->all();
    }

    /**
     * @param  array<int|string, mixed>  $raw
     * @return array<int, int>
     */
    private function normalizarOrigensPorRow(array $raw): array
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

    private function validarEmpresaOrigemImportacao(int $idEmpresaOrigem, ?User $user, ?int $idUnidadeEstoque = null): ?string
    {
        $empresa = Empresa::query()->with('entidade')->find($idEmpresaOrigem);
        if ($empresa === null) {
            return 'Unidade de origem não encontrada.';
        }

        $unidade = $empresa->entidade;
        if (! $unidade instanceof UnidadeNegocio || ! $unidade->possui_estoque) {
            return 'Origem comercial deve ser uma unidade de negócio que controla estoque.';
        }

        if ($unidade->is_hub) {
            return 'Origem comercial não pode ser HUB. Selecione a loja e informe o HUB em saída física.';
        }

        if ($user !== null && ! app(UnidadeNegocioAccessService::class)->canVenda($user, $unidade->id)) {
            return UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO;
        }

        if ($idUnidadeEstoque !== null && $idUnidadeEstoque > 0) {
            $estoque = UnidadeNegocio::query()->find($idUnidadeEstoque);
            if ($estoque === null || ! $estoque->possui_estoque) {
                return 'Unidade de saída física inválida.';
            }

            if ($user !== null && ! app(UnidadeNegocioAccessService::class)->canAccess($user, $estoque->id)) {
                return UnidadeNegocioAccessService::MENSAGEM_SEM_ACESSO;
            }
        }

        return null;
    }

    private function removerArquivoTemporario(VendaImportacao $importacao): void
    {
        try {
            if ($importacao->arquivo_path !== '' && Storage::disk('local')->exists($importacao->arquivo_path)) {
                Storage::disk('local')->delete($importacao->arquivo_path);
            }
        } catch (Throwable) {
        }
    }

    private function mensagemErroIniciarImportacao(Throwable $e): string
    {
        $msg = $e->getMessage();
        if (str_contains($msg, 'venda_importacoes') || str_contains($msg, 'Base table or view not found')) {
            return 'Estrutura de importação não encontrada no banco. Execute php artisan migrate no servidor e tente novamente.';
        }

        return 'Não foi possível iniciar a importação. Verifique o arquivo ou contate o suporte.';
    }
}
