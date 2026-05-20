# PLAN-0028: Backup diário do MySQL às 01:00

**ADR:** [ADR-0028](../decisions/ADR-0028-backup-mysql-diario-01h.md)
**Data:** 2026-05-20
**Status:** Concluído

## Objetivo

Gerar dump completo do MySQL todo dia à 1h (horário de Brasília) e manter retenção configurável.

## Pré-requisitos

- Docker Compose com serviços `mysql` e app Laravel.
- Variáveis `DB_*` no `.env`.

## Passos

1. **Config** — `config/backup.php` e variáveis em `.env.example`.
2. **Comando** — `app/Console/Commands/DatabaseBackupCommand.php` (`db:backup`).
3. **Agenda** — `bootstrap/app.php` → `withSchedule` às 01:00.
4. **Docker** — `default-mysql-client` no `Dockerfile`; serviço `scheduler` no `docker-compose.yml`.
5. **Script manual** — `scripts/backup/mysql-dump.sh` (host).
6. **Subir scheduler** — `docker compose up -d --build scheduler` (ou rebuild da app se só mudou Dockerfile).

## Critério de conclusão

- `php artisan db:backup` gera `storage/app/backups/database/{database}_YYYY-MM-DD_HHMMSS.sql.gz`.
- `php artisan schedule:list` mostra `db:backup` às 01:00 em `America/Sao_Paulo`.
- Serviço `controladoriasb-scheduler` em execução após `docker compose up -d scheduler`.

## Riscos

- Disco cheio — mitigar com `BACKUP_RETENTION_DAYS` e monitorar `storage/app/backups`.
- Dump longo em base grande — `BACKUP_TIMEOUT` e `withoutOverlapping` no schedule.
