<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup automático do MySQL
    |--------------------------------------------------------------------------
    |
    | O agendamento roda via `php artisan schedule:work` (serviço scheduler no
    | docker-compose) ou cron no host: * * * * * php artisan schedule:run
    |
    */

    'enabled' => env('BACKUP_ENABLED', true),

    /** Pasta relativa a storage/app/ */
    'path' => env('BACKUP_PATH', 'backups/database'),

    /** Quantos dias de arquivos .sql.gz manter */
    'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),

    /** Horário no fuso de config('app.timezone') — padrão 01:00 */
    'daily_at' => env('BACKUP_DAILY_AT', '01:00'),

    /** Timeout do mysqldump em segundos */
    'timeout' => (int) env('BACKUP_TIMEOUT', 3600),

];
