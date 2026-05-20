# ADR-0028: Backup diário do MySQL às 01:00

**Data:** 2026-05-20
**Status:** Aceito
**Contexto:** Necessidade de rotina automática de cópia completa do banco em ambiente Docker.

## Contexto

O sistema roda com MySQL 8 em `docker-compose` (serviço `mysql`). Não havia backup agendado; dumps manuais eram arriscados e irregulares.

## Decisão

- Comando `php artisan db:backup`: `mysqldump` + `gzip` em `storage/app/backups/database/`.
- Agendamento Laravel `dailyAt('01:00')` com `timezone` = `config('app.timezone')` (`America/Sao_Paulo`).
- Serviço Docker `scheduler` com `php artisan schedule:work` (equivalente a cron `* * * * * schedule:run`).
- Retenção padrão de **14 dias** (`BACKUP_RETENTION_DAYS`); arquivos antigos removidos após cada backup.
- Imagem da app inclui `default-mysql-client` para o dump dentro do container.
- Script alternativo no host: `scripts/backup/mysql-dump.sh` (usa `docker compose exec` no container MySQL).

## Alternativas consideradas

- **Cron no host** — rejeitado como único mecanismo: depende de configuração manual fora do compose; mantido apenas o script shell como fallback.
- **Pacote spatie/laravel-backup** — rejeitado: escopo maior (S3, notificações); desnecessário para dump local diário.
- **Volume snapshot do MySQL** — rejeitado: exige infraestrutura de storage/orquestração além do projeto atual.

## Consequências

- É preciso subir o serviço `scheduler` em produção (`docker compose up -d scheduler`).
- Backups ficam no disco do host via bind `./storage`; planejar cópia externa (NAS/cloud) separadamente.
- Log do agendamento em `storage/logs/backup.log`.
