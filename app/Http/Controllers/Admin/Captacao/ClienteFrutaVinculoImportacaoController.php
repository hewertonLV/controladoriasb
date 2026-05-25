<?php

namespace App\Http\Controllers\Admin\Captacao;

use App\Http\Controllers\Admin\Concerns\AutorizaProcessamentoUsuario;
use App\Http\Controllers\Controller;
use App\Jobs\Captacao\ProcessarPreviewImportacaoClienteFrutasJob;
use App\Models\Captacao\ClienteFrutaImportacao;
use App\Models\Cliente;
use App\Models\UnidadeNegocio;
use App\Services\Captacao\ClienteFrutaVinculoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ClienteFrutaVinculoImportacaoController extends Controller
{
    use AutorizaProcessamentoUsuario;

    public function __construct(
        private readonly ClienteFrutaVinculoService $vinculos,
    ) {}

    public function importar(Request $request): View
    {
        $faturamentoId = (int) $request->query('faturamento', 0);

        $faturamentos = UnidadeNegocio::query()
            ->where('emite_nota_fiscal', true)
            ->where('is_hub', false)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        if ($faturamentoId < 1 && $faturamentos->isNotEmpty()) {
            $faturamentoId = (int) $faturamentos->first()->id;
        }

        $unidade = $faturamentos->firstWhere('id', $faturamentoId);

        return view('admin.captacao.cliente-frutas.importar', [
            'faturamentos' => $faturamentos,
            'faturamentoId' => $faturamentoId,
            'faturamentoNome' => $unidade?->nome,
        ]);
    }

    public function iniciar(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'arquivo' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
            'faturamento' => ['required', 'integer', 'exists:unidades_negocio,id'],
        ], [
            'arquivo.required' => 'Selecione um arquivo .xlsx ou .xls.',
            'arquivo.mimes' => 'O arquivo precisa ser .xlsx ou .xls.',
            'arquivo.max' => 'O arquivo pode ter no máximo 5 MB.',
            'faturamento.required' => 'Selecione a unidade de faturamento.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Dados inválidos.',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $faturamentoId = (int) $request->input('faturamento');

        if (! UnidadeNegocio::query()
            ->whereKey($faturamentoId)
            ->where('emite_nota_fiscal', true)
            ->where('is_hub', false)
            ->exists()) {
            return response()->json([
                'message' => 'Unidade de faturamento inválida.',
            ], 422);
        }

        $user = $request->user();
        $file = $request->file('arquivo');
        $original = $file?->getClientOriginalName();

        $path = $file?->store('captacao/cliente-frutas/importacoes', 'local');
        if ($path === null || $path === false) {
            return response()->json([
                'message' => 'Falha ao salvar o arquivo enviado.',
            ], 500);
        }

        $importacao = ClienteFrutaImportacao::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'id_unidade_negocio_faturamento' => $faturamentoId,
            'arquivo_original' => $original,
            'arquivo_path' => $path,
            'status' => ClienteFrutaImportacao::STATUS_AGUARDANDO,
        ]);

        ProcessarPreviewImportacaoClienteFrutasJob::dispatch($importacao->id);

        return response()->json([
            'uuid' => $importacao->uuid,
            'status' => $importacao->status,
            'urls' => [
                'status' => route('admin.captacao.frutas-por-loja.importar.status', $importacao),
                'resultado' => route('admin.captacao.frutas-por-loja.importar.resultado', $importacao),
                'confirmar' => route('admin.captacao.frutas-por-loja.importar.confirmar', $importacao),
            ],
        ], 202);
    }

    public function status(Request $request, ClienteFrutaImportacao $importacao): JsonResponse
    {
        $this->autorizarAcessoProcessamento($request, $importacao);

        return response()->json($this->statusPayload($importacao));
    }

    public function resultado(Request $request, ClienteFrutaImportacao $importacao): JsonResponse
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

    public function confirmar(Request $request, ClienteFrutaImportacao $importacao): JsonResponse
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
                'message' => 'Nenhum vínculo foi selecionado para importação.',
            ], 422);
        }

        @set_time_limit(900);

        $resultado = $importacao->resultado ?? [];
        $novasIndex = $this->indexarPorRowId($resultado['novas'] ?? []);

        $vinculosCriados = 0;
        $ignoradas = 0;
        $erros = [];

        try {
            DB::transaction(function () use (
                $rowIdsNovas,
                $novasIndex,
                $importacao,
                &$vinculosCriados,
                &$ignoradas,
                &$erros,
            ): void {
                foreach ($rowIdsNovas as $rowId) {
                    $item = $novasIndex[$rowId] ?? null;
                    if ($item === null) {
                        $ignoradas++;

                        continue;
                    }

                    $idCliente = (int) ($item['id_cliente'] ?? 0);
                    $idFruta = (int) ($item['id_fruta'] ?? 0);

                    $cliente = Cliente::query()
                        ->whereKey($idCliente)
                        ->where('id_unidade_negocio', $importacao->id_unidade_negocio_faturamento)
                        ->first();

                    if ($cliente === null || $idFruta < 1) {
                        $ignoradas++;

                        continue;
                    }

                    if ($this->vinculos->clientePossuiFruta($cliente->id, $idFruta)) {
                        $ignoradas++;

                        continue;
                    }

                    $this->vinculos->adicionarFrutas($cliente, [$idFruta]);
                    $vinculosCriados++;
                }
            });
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Erro ao gravar vínculos: '.$e->getMessage(),
            ], 500);
        }

        $this->removerArquivoTemporario($importacao);

        return response()->json([
            'message' => 'Importação concluída.',
            'resumo' => [
                'vinculos_criados' => $vinculosCriados,
                'ignoradas' => $ignoradas,
                'erros' => $erros,
            ],
            'redirect' => route('admin.captacao.frutas-por-loja.index', [
                'faturamento' => $importacao->id_unidade_negocio_faturamento,
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function statusPayload(ClienteFrutaImportacao $importacao): array
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

    private function removerArquivoTemporario(ClienteFrutaImportacao $importacao): void
    {
        try {
            if ($importacao->arquivo_path !== '' && Storage::disk('local')->exists($importacao->arquivo_path)) {
                Storage::disk('local')->delete($importacao->arquivo_path);
            }
        } catch (Throwable) {
        }
    }
}
