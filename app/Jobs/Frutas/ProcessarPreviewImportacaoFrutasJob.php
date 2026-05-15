<?php

namespace App\Jobs\Frutas;

use App\Models\FrutaImportacao;
use App\Services\Frutas\FrutaImportacaoProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessarPreviewImportacaoFrutasJob implements ShouldQueue
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

    public function handle(FrutaImportacaoProcessor $processor): void
    {
        $importacao = FrutaImportacao::query()->find($this->importacaoId);
        if ($importacao === null) {
            return;
        }

        if ($importacao->isFinalizado()) {
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
        $importacao = FrutaImportacao::query()->find($this->importacaoId);
        if ($importacao === null) {
            return;
        }

        if (! $importacao->isFinalizado()) {
            $importacao->forceFill([
                'status' => FrutaImportacao::STATUS_FALHOU,
                'finished_at' => now(),
                'erro_mensagem' => 'Job de importação falhou: '.$e->getMessage(),
            ])->save();
        }
    }
}
