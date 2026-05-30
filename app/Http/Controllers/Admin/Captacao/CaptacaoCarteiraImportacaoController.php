<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Http\Controllers\Admin\Concerns\AutorizaProcessamentoUsuario;
use App\Http\Controllers\Controller;
use App\Jobs\Captacao\ProcessarPreviewImportacaoCaptacaoCarteiraJob;
use App\Models\Captacao\CaptacaoCarteira;
use App\Models\Captacao\CaptacaoCarteiraImportacao;
use App\Services\Captacao\CaptacaoCarteiraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class CaptacaoCarteiraImportacaoController extends Controller
{
    use AutorizaProcessamentoUsuario;

    public function __construct(
        private readonly CaptacaoCarteiraService $carteiras,
    ) {}

    public function importar(CaptacaoCarteira $carteira): View
    {
        $carteira->load(['unidadeFaturamento:id,nome']);

        return view('admin.captacao.carteiras.importar-lojas', [
            'carteira' => $carteira,
        ]);
    }

    public function iniciar(Request $request, CaptacaoCarteira $carteira): JsonResponse
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
                'message' => 'Dados inválidos.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $user = $request->user();
        $file = $request->file('arquivo');
        $original = $file?->getClientOriginalName();

        $path = $file?->store('captacao/carteiras/importacoes', 'local');
        if ($path === null || $path === false) {
            return response()->json([
                'message' => 'Falha ao salvar o arquivo enviado.',
            ], 500);
        }

        $importacao = CaptacaoCarteiraImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'id_captacao_carteira' => $carteira->id,
            'arquivo_original' => $original,
            'arquivo_path' => $path,
            'status' => CaptacaoCarteiraImportacao::STATUS_AGUARDANDO,
        ]);

        ProcessarPreviewImportacaoCaptacaoCarteiraJob::dispatch($importacao->id);

        return response()->json([
            'uuid' => $importacao->uuid,
            'status' => $importacao->status,
            'urls' => [
                'status' => route('admin.captacao.carteiras.importar-lojas.status', [$carteira, $importacao]),
                'resultado' => route('admin.captacao.carteiras.importar-lojas.resultado', [$carteira, $importacao]),
                'confirmar' => route('admin.captacao.carteiras.importar-lojas.confirmar', [$carteira, $importacao]),
            ],
        ], 202);
    }

    public function status(Request $request, CaptacaoCarteira $carteira, CaptacaoCarteiraImportacao $importacoe): JsonResponse
    {
        $this->assertImportacaoDaCarteira($carteira, $importacoe);
        $this->autorizarAcessoProcessamento($request, $importacoe);

        return response()->json($this->statusPayload($importacoe));
    }

    public function resultado(Request $request, CaptacaoCarteira $carteira, CaptacaoCarteiraImportacao $importacoe): JsonResponse
    {
        $this->assertImportacaoDaCarteira($carteira, $importacoe);
        $this->autorizarAcessoProcessamento($request, $importacoe);

        if (! $importacoe->isConcluido()) {
            return response()->json([
                'message' => 'A importação ainda não foi concluída.',
                'status' => $importacoe->status,
            ], 409);
        }

        $resultado = $importacoe->resultado ?? [];

        return response()->json([
            'status' => $importacoe->status,
            'novas' => $resultado['novas'] ?? [],
            'atualizacoes' => $resultado['atualizacoes'] ?? [],
            'sem_alteracoes' => $resultado['sem_alteracoes'] ?? [],
            'erros' => $resultado['erros'] ?? [],
        ]);
    }

    public function confirmar(Request $request, CaptacaoCarteira $carteira, CaptacaoCarteiraImportacao $importacoe): JsonResponse
    {
        $this->assertImportacaoDaCarteira($carteira, $importacoe);
        $this->autorizarAcessoProcessamento($request, $importacoe);

        if (! $importacoe->isConcluido()) {
            return response()->json([
                'message' => 'A análise da importação ainda não terminou.',
                'status' => $importacoe->status,
            ], 409);
        }

        $payload = $request->validate([
            'row_ids_novas' => ['nullable', 'array'],
            'row_ids_novas.*' => ['integer', 'min:1'],
        ]);

        $rowIdsNovas = array_values(array_unique(array_map('intval', $payload['row_ids_novas'] ?? [])));

        if ($rowIdsNovas === []) {
            return response()->json([
                'message' => 'Nenhuma loja foi selecionada para vincular.',
            ], 422);
        }

        @set_time_limit(900);

        $resultado = $importacoe->resultado ?? [];
        $novasIndex = $this->indexarPorRowId($resultado['novas'] ?? []);

        $vinculados = 0;
        $ignoradas = 0;

        try {
            DB::transaction(function () use (
                $rowIdsNovas,
                $novasIndex,
                $carteira,
                &$vinculados,
                &$ignoradas,
            ): void {
                $idsClientes = [];

                foreach ($rowIdsNovas as $rowId) {
                    $item = $novasIndex[$rowId] ?? null;
                    if ($item === null) {
                        $ignoradas++;

                        continue;
                    }

                    $idCliente = (int) ($item['id_cliente'] ?? 0);
                    if ($idCliente < 1) {
                        $ignoradas++;

                        continue;
                    }

                    $idsClientes[] = $idCliente;
                }

                $vinculados = $this->carteiras->adicionarLojas($carteira, $idsClientes);
            });
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Erro ao vincular lojas: '.$e->getMessage(),
            ], 500);
        }

        $this->removerArquivoTemporario($importacoe);

        return response()->json([
            'message' => 'Importação concluída.',
            'resumo' => [
                'lojas_vinculadas' => $vinculados,
                'ignoradas' => $ignoradas,
            ],
            'redirect' => route('admin.captacao.carteiras.edit', $carteira),
        ]);
    }

    private function assertImportacaoDaCarteira(CaptacaoCarteira $carteira, CaptacaoCarteiraImportacao $importacao): void
    {
        if ((int) $importacao->id_captacao_carteira !== (int) $carteira->id) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function statusPayload(CaptacaoCarteiraImportacao $importacao): array
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

    private function removerArquivoTemporario(CaptacaoCarteiraImportacao $importacao): void
    {
        try {
            if ($importacao->arquivo_path !== '' && Storage::disk('local')->exists($importacao->arquivo_path)) {
                Storage::disk('local')->delete($importacao->arquivo_path);
            }
        } catch (Throwable) {
        }
    }
}
