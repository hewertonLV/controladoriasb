<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class DatabaseBackupScheduleTest extends TestCase
{
    public function test_db_backup_esta_agendado_as_01h_em_brasilia(): void
    {
        config([
            'backup.enabled' => true,
            'backup.daily_at' => '01:00',
            'app.timezone' => 'America/Sao_Paulo',
        ]);

        $this->artisan('db:backup --help');

        $schedule = app(Schedule::class);
        $event = collect($schedule->events())->first(
            fn ($scheduled) => str_contains((string) ($scheduled->command ?? ''), 'db:backup'),
        );

        $this->assertNotNull($event, 'Evento db:backup não encontrado no schedule.');
        $this->assertSame('0 1 * * *', $event->expression);
        $this->assertSame('America/Sao_Paulo', $event->timezone);
    }

    public function test_comando_db_backup_esta_registrado(): void
    {
        $this->artisan('db:backup --help')
            ->assertExitCode(0);
    }
}
