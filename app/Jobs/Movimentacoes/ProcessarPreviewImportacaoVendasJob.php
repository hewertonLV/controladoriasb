<?php

namespace App\Jobs\Movimentacoes;

use App\Models\VendaImportacao;
use App\Services\Movimentacoes\VendaImportacaoProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessarPreviewImportacaoVendasJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public readonly int $importacaoId)
    {
        $this->onQueue('vendas-importacao');
    }

    public function handle(VendaImportacaoProcessor $processor): void
    {
        $importacao = VendaImportacao::query()->find($this->importacaoId);
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
        $importacao = VendaImportacao::query()->find($this->importacaoId);
        if ($importacao === null) {
            return;
        }

        if (! $importacao->isFinalizado()) {
            $importacao->forceFill([
                'status' => VendaImportacao::STATUS_FALHOU,
                'finished_at' => now(),
                'erro_mensagem' => 'Job de importação falhou: '.$e->getMessage(),
            ])->save();
        }
    }
}
