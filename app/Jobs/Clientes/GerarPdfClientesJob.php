<?php

namespace App\Jobs\Clientes;

use App\Models\Cliente;
use App\Models\ClienteExportacao;
use App\Models\User;
use App\Queries\ClienteQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GerarPdfClientesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const LIMITE_REGISTROS_PDF = 1000;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public readonly int $exportacaoId)
    {
        $this->onQueue('clientes-exportacao');
    }

    public function handle(ClienteQuery $clienteQuery): void
    {
        $exportacao = ClienteExportacao::query()->find($this->exportacaoId);

        if ($exportacao === null || $exportacao->isFinalizado()) {
            return;
        }

        @set_time_limit(900);
        ini_set('memory_limit', '512M');

        $exportacao->forceFill([
            'status' => ClienteExportacao::STATUS_PROCESSANDO,
            'started_at' => $exportacao->started_at ?? now(),
            'erro_mensagem' => null,
        ])->save();

        try {
            $filtros = $clienteQuery->normalizarFiltros($exportacao->filtros ?? []);
            $query = $clienteQuery->aplicarFiltros(
                Cliente::query()
                    ->select([
                        'id',
                        'id_cigam',
                        'razao_social',
                        'cnpj_cpf',
                        'id_unidade_negocio',
                        'id_praca',
                        'grupo_id',
                        'desconto_nf',
                        'desconto_contrato',
                    ])
                    ->with(['praca:id,nome', 'grupo:id,nome']),
                $filtros,
            );
            $total = (clone $query)->toBase()->count();

            if ($total > self::LIMITE_REGISTROS_PDF) {
                $exportacao->forceFill(['total_registros' => $total])->save();

                throw new \RuntimeException(
                    'A exportação PDF suporta até '.self::LIMITE_REGISTROS_PDF.' registros por vez. Utilize filtros para reduzir o resultado.',
                );
            }

            $clientes = $query->get();

            $geradoEm = now();
            $arquivoNome = 'clientes_'.$geradoEm->format('Ymd_His').'_'.$exportacao->uuid.'.pdf';
            $arquivoPath = 'clientes/exportacoes/'.$arquivoNome;
            $geradoPor = $exportacao->user_id !== null
                ? User::query()->whereKey($exportacao->user_id)->value('name')
                : null;

            $pdf = Pdf::loadView('admin.clientes.pdf', [
                'clientes' => $clientes,
                'filtros' => $filtros,
                'geradoEm' => $geradoEm,
                'geradoPor' => $geradoPor ?: '—',
                'limiteRegistros' => self::LIMITE_REGISTROS_PDF,
            ])
                ->setPaper('a4', 'landscape')
                ->setOptions([
                    'defaultFont' => 'Helvetica',
                    'dpi' => 96,
                    'isRemoteEnabled' => false,
                    'isFontSubsettingEnabled' => true,
                ]);

            Storage::disk('local')->put($arquivoPath, $pdf->output());

            $exportacao->forceFill([
                'status' => ClienteExportacao::STATUS_CONCLUIDO,
                'arquivo_path' => $arquivoPath,
                'arquivo_nome' => $arquivoNome,
                'total_registros' => $total,
                'finished_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            $this->marcarFalha($exportacao, $e);
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $exportacao = ClienteExportacao::query()->find($this->exportacaoId);

        if ($exportacao !== null && ! $exportacao->isFinalizado()) {
            $this->marcarFalha($exportacao, $e);
        }
    }

    private function marcarFalha(ClienteExportacao $exportacao, Throwable $e): void
    {
        Log::warning('Exportação PDF de Clientes falhou', [
            'exportacao_id' => $exportacao->id,
            'uuid' => $exportacao->uuid,
            'erro' => $e->getMessage(),
        ]);

        $exportacao->forceFill([
            'status' => ClienteExportacao::STATUS_FALHOU,
            'finished_at' => now(),
            'erro_mensagem' => $this->mensagemAmigavel($e),
        ])->save();
    }

    private function mensagemAmigavel(Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'Allowed memory size')) {
            return 'A geração do PDF excedeu o limite de memória. Aplique filtros para reduzir o relatório ou aumente o memory_limit do worker.';
        }

        if (str_contains($message, 'A exportação PDF suporta até')) {
            return $message;
        }

        if (str_contains($message, 'Maximum execution time')) {
            return 'A geração do PDF excedeu o tempo limite. Ajuste o timeout do worker para 900s ou reduza o volume do relatório.';
        }

        return 'Falha ao gerar o PDF: '.$message;
    }
}
