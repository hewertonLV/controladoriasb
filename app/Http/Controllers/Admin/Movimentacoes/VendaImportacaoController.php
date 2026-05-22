<?php

namespace App\Http\Controllers\Admin\Movimentacoes;

use App\Http\Controllers\Admin\Concerns\AutorizaProcessamentoUsuario;
use App\Http\Controllers\Controller;
use App\Jobs\Movimentacoes\ProcessarPreviewImportacaoVendasJob;
use App\Models\VendaImportacao;
use App\Services\Movimentacoes\VendaMovimentacaoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $importacao = VendaImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'arquivo_original' => $original,
            'arquivo_path' => $path,
            'status' => VendaImportacao::STATUS_AGUARDANDO,
        ]);

        ProcessarPreviewImportacaoVendasJob::dispatch($importacao->id);

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
        ]);

        $rowIdsNovas = array_values(array_unique(array_map('intval', $payload['row_ids_novas'] ?? [])));

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
                $request,
                $rowIdsNovas,
                $novasIndex,
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
                    $chaveGrupo = implode('|', [
                        (string) ($dados['numero_nf'] ?? ''),
                        (int) ($dados['id_empresa_origem'] ?? 0),
                        (int) ($dados['id_empresa_destino'] ?? 0),
                    ]);

                    $grupos[$chaveGrupo][] = $item;
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

                    try {
                        $resultadoVenda = $this->vendas->registrarVenda([
                            'numero_nf' => (string) ($primeiro['numero_nf'] ?? ''),
                            'id_empresa_origem' => (int) ($primeiro['id_empresa_origem'] ?? 0),
                            'id_empresa_destino' => (int) ($primeiro['id_empresa_destino'] ?? 0),
                            'itens' => $itensVenda,
                        ], $request->user());

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

    private function removerArquivoTemporario(VendaImportacao $importacao): void
    {
        try {
            if ($importacao->arquivo_path !== '' && Storage::disk('local')->exists($importacao->arquivo_path)) {
                Storage::disk('local')->delete($importacao->arquivo_path);
            }
        } catch (Throwable) {
        }
    }
}
