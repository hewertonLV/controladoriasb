<?php

namespace App\Jobs\Veiculos;

use App\Models\User;
use App\Models\Veiculo;
use App\Models\VeiculoExportacao;
use App\Queries\VeiculoQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Support\PrivateStorage;
use Throwable;

class GerarPdfVeiculosJob implements ShouldQueue
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
        $this->onQueue('veiculos-exportacao');
    }

    public function handle(VeiculoQuery $veiculoQuery): void
    {
        $exportacao = VeiculoExportacao::query()->find($this->exportacaoId);

        if ($exportacao === null || $exportacao->isFinalizado()) {
            return;
        }

        @set_time_limit(900);
        ini_set('memory_limit', '512M');

        $exportacao->forceFill([
            'status' => VeiculoExportacao::STATUS_PROCESSANDO,
            'started_at' => $exportacao->started_at ?? now(),
            'erro_mensagem' => null,
        ])->save();

        try {
            $filtros = $veiculoQuery->normalizarFiltros($exportacao->filtros ?? []);
            $query = $veiculoQuery->aplicarFiltros(
                Veiculo::query()->select([
                    'id',
                    'id_sbs',
                    'nome',
                    'tipo',
                    'id_unidade_negocio',
                    'status',
                ])->with('unidadeNegocio:id,nome'),
                $filtros,
            );
            $total = (clone $query)->toBase()->count();

            if ($total > self::LIMITE_REGISTROS_PDF) {
                $exportacao->forceFill(['total_registros' => $total])->save();

                throw new \RuntimeException(
                    'A exportação PDF suporta até '.self::LIMITE_REGISTROS_PDF.' registros por vez. Utilize filtros para reduzir o resultado.',
                );
            }

            $veiculos = $query->get();

            $geradoEm = now();
            $arquivoNome = 'veiculos_'.$geradoEm->format('Ymd_His').'_'.$exportacao->uuid.'.pdf';
            $arquivoPath = 'veiculos/exportacoes/'.$arquivoNome;
            $geradoPor = $exportacao->user_id !== null
                ? User::query()->whereKey($exportacao->user_id)->value('name')
                : null;

            $pdf = Pdf::loadView('admin.veiculos.pdf', [
                'veiculos' => $veiculos,
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

            PrivateStorage::put($arquivoPath, $pdf->output());

            $exportacao->forceFill([
                'status' => VeiculoExportacao::STATUS_CONCLUIDO,
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
        $exportacao = VeiculoExportacao::query()->find($this->exportacaoId);

        if ($exportacao !== null && ! $exportacao->isFinalizado()) {
            $this->marcarFalha($exportacao, $e);
        }
    }

    private function marcarFalha(VeiculoExportacao $exportacao, Throwable $e): void
    {
        Log::warning('Exportação PDF de Veículos falhou', [
            'exportacao_id' => $exportacao->id,
            'uuid' => $exportacao->uuid,
            'erro' => $e->getMessage(),
        ]);

        $exportacao->forceFill([
            'status' => VeiculoExportacao::STATUS_FALHOU,
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
