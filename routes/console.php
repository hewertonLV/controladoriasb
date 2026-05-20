<?php

use App\Services\Empresas\EmpresaRegistryBackfillService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('db:backup')
    ->dailyAt(config('backup.daily_at', '01:00'))
    ->timezone(config('app.timezone', 'America/Sao_Paulo'))
    ->when(fn (): bool => (bool) config('backup.enabled', true))
    ->withoutOverlapping((int) config('backup.timeout', 3600))
    ->appendOutputTo(storage_path('logs/backup.log'));

Artisan::command('empresas:backfill-registry {--audit : Registrar histórico para cada vínculo criado}', function (): void {
    $audit = (bool) $this->option('audit');
    $n = app(EmpresaRegistryBackfillService::class)->executar(registrarHistorico: $audit);
    $this->info("Vínculos criados ou verificados: {$n}");
})->purpose('Garante linhas em empresas para todos os clientes, fornecedores e unidades de negócio.');
