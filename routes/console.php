<?php

use App\Services\Empresas\EmpresaRegistryBackfillService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('empresas:backfill-registry {--audit : Registrar histórico para cada vínculo criado}', function (): void {
    $audit = (bool) $this->option('audit');
    $n = app(EmpresaRegistryBackfillService::class)->executar(registrarHistorico: $audit);
    $this->info("Vínculos criados ou verificados: {$n}");
})->purpose('Garante linhas em empresas para todos os clientes, fornecedores e unidades de negócio.');
