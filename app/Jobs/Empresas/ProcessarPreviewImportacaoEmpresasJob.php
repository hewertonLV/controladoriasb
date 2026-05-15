<?php

namespace App\Jobs\Empresas;

use App\Models\EmpresaImportacao;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Mantido apenas para jobs antigos na fila. Novo fluxo não despacha este job.
 */
class ProcessarPreviewImportacaoEmpresasJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;

    public int $tries = 1;

    public function __construct(public readonly int $importacaoId)
    {
        $this->onQueue('empresas-importacao');
    }

    public function handle(): void
    {
        $importacao = EmpresaImportacao::query()->find($this->importacaoId);
        if ($importacao === null || $importacao->isFinalizado()) {
            return;
        }

        $importacao->forceFill([
            'status' => EmpresaImportacao::STATUS_FALHOU,
            'finished_at' => now(),
            'erro_mensagem' => 'Importação de planilha neste módulo foi descontinuada. Use Clientes, Fornecedores ou Unidades de negócio — o hub corporativo é atualizado automaticamente.',
        ])->save();
    }

    public function failed(Throwable $e): void
    {
        $this->handle();
    }
}
