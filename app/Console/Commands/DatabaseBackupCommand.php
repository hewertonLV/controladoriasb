<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup
                            {--retention= : Dias para manter arquivos antigos (sobrescreve BACKUP_RETENTION_DAYS)}';

    protected $description = 'Gera dump completo do MySQL (gzip) em storage/app/backups/database';

    public function handle(): int
    {
        if (! config('backup.enabled', true)) {
            $this->warn('Backup desabilitado (BACKUP_ENABLED=false).');

            return self::SUCCESS;
        }

        if (! $this->mysqldumpAvailable()) {
            $this->error('mysqldump não encontrado. Instale default-mysql-client no container ou execute scripts/backup/mysql-dump.sh no host.');

            return self::FAILURE;
        }

        $connection = config('database.connections.'.config('database.default'));
        $database = $connection['database'] ?? null;
        $host = $connection['host'] ?? '127.0.0.1';
        $port = (int) ($connection['port'] ?? 3306);
        $user = $connection['username'] ?? 'root';
        $password = (string) ($connection['password'] ?? '');

        if ($database === null || $database === '') {
            $this->error('DB_DATABASE não configurado.');

            return self::FAILURE;
        }

        $backupDir = storage_path('app/'.trim(config('backup.path', 'backups/database'), '/'));
        File::ensureDirectoryExists($backupDir);

        $filename = sprintf('%s_%s.sql.gz', $database, now()->format('Y-m-d_His'));
        $targetPath = $backupDir.'/'.$filename;

        $this->info("Gerando backup: {$targetPath}");

        $process = $this->buildDumpProcess($host, $port, $user, $password, $database, $targetPath);
        $process->setTimeout((int) config('backup.timeout', 3600));

        try {
            $process->mustRun(function (string $type, string $buffer): void {
                if ($type === Process::ERR && $buffer !== '') {
                    $this->output->write($buffer);
                }
            });
        } catch (ProcessFailedException $exception) {
            $this->error('Falha no mysqldump: '.$exception->getMessage());

            if (is_file($targetPath)) {
                @unlink($targetPath);
            }

            return self::FAILURE;
        }

        $sizeMb = round(filesize($targetPath) / 1024 / 1024, 2);
        $this->info("Backup concluído ({$sizeMb} MB).");

        $removed = $this->pruneOldBackups($backupDir, $this->retentionDays());
        if ($removed > 0) {
            $this->line("Removidos {$removed} backup(s) antigo(s).");
        }

        return self::SUCCESS;
    }

    private function mysqldumpAvailable(): bool
    {
        $check = new Process(['which', 'mysqldump']);
        $check->run();

        return $check->isSuccessful();
    }

    private function buildDumpProcess(
        string $host,
        int $port,
        string $user,
        string $password,
        string $database,
        string $targetPath,
    ): Process {
        $shell = sprintf(
            'mysqldump -h %s -P %d -u %s --single-transaction --routines --triggers --events %s 2>/dev/null | gzip -9 > %s',
            escapeshellarg($host),
            $port,
            escapeshellarg($user),
            escapeshellarg($database),
            escapeshellarg($targetPath),
        );

        $env = array_filter([
            'MYSQL_PWD' => $password !== '' ? $password : null,
        ]);

        return Process::fromShellCommandline($shell, null, $env);
    }

    private function pruneOldBackups(string $directory, int $retentionDays): int
    {
        if ($retentionDays < 1) {
            return 0;
        }

        $cutoff = now()->subDays($retentionDays)->getTimestamp();
        $removed = 0;

        foreach (glob($directory.'/*.sql.gz') ?: [] as $file) {
            if (! is_file($file)) {
                continue;
            }
            if (filemtime($file) < $cutoff) {
                if (@unlink($file)) {
                    $removed++;
                }
            }
        }

        return $removed;
    }

    private function retentionDays(): int
    {
        $option = $this->option('retention');

        if ($option !== null && $option !== '') {
            return max(1, (int) $option);
        }

        return max(1, (int) config('backup.retention_days', 14));
    }
}
