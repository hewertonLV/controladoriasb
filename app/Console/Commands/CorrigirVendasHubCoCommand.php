<?php

namespace App\Console\Commands;

use App\Services\Movimentacoes\CorrigirCustosVendaSaidaHubService;
use Illuminate\Console\Command;

final class CorrigirVendasHubCoCommand extends Command
{
    protected $signature = 'movimentacoes:corrigir-vendas-hub-co
                            {--nf= : Número da NF (ex.: CAP-20260527-14-116)}
                            {--dry-run : Apenas contar vendas que seriam corrigidas}';

    protected $description = 'Recalcula custo de saída e observação de vendas com saída física HUB (ADR-0135/0139)';

    public function handle(CorrigirCustosVendaSaidaHubService $service): int
    {
        $nf = $this->option('nf');
        $dryRun = (bool) $this->option('dry-run');

        $corrigidas = $service->corrigirNota(
            is_string($nf) && $nf !== '' ? $nf : null,
            $dryRun,
        );

        if ($dryRun) {
            $this->info("Vendas HUB a corrigir: {$corrigidas}");

            return self::SUCCESS;
        }

        $this->info("Vendas HUB corrigidas: {$corrigidas}");

        return self::SUCCESS;
    }
}
