<?php

namespace App\Jobs\Frutas;

use App\Models\FrutaIcmsImportacao;
use App\Services\Frutas\FrutaIcmsImportacaoProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessarPreviewImportacaoFrutasIcmsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public readonly int $importacaoId)
    {
        $this->onQueue('frutas-importacao');
    }

    public function handle(FrutaIcmsImportacaoProcessor $processor): void
    {
        $importacao = FrutaIcmsImportacao::query()->find($this->importacaoId);
        if ($importacao === null || $importacao->isFinalizado()) {
            return;
        }

        @set_time_limit(900);

        try {
            $processor->processar($importacao);
        } catch (Throwable $e) {
            $processor->marcarFalha($importacao, $e);
            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $importacao = FrutaIcmsImportacao::query()->find($this->importacaoId);
        if ($importacao !== null && ! $importacao->isFinalizado()) {
            $importacao->forceFill([
                'status' => FrutaIcmsImportacao::STATUS_FALHOU,
                'finished_at' => now(),
                'erro_mensagem' => 'Job de importação de ICMS falhou: '.$e->getMessage(),
            ])->save();
        }
    }
}
