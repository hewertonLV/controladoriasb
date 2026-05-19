# PLAN-0023: Fuso horário de Brasília (mesmo da ADR)

**ADR:** [ADR-0023](../decisions/ADR-0023-fuso-horario-brasilia.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Gravar e exibir datas/horas no fuso de Brasília (UTC−3) em toda a stack Laravel + MySQL + Docker.

## Pré-requisitos

- Acesso ao `.env` do ambiente local/produção.

## Passos

1. **Config Laravel** — `config/app.php` lê `APP_TIMEZONE`; padrão `America/Sao_Paulo`.
2. **Config MySQL** — `config/database.php` define `timezone` com `DB_TIMEZONE` (padrão `-03:00`).
3. **Docker** — `TZ` nos serviços PHP; `--default-time-zone=-03:00` no MySQL.
4. **`.env`** — definir `APP_TIMEZONE` e `DB_TIMEZONE`; rodar `php artisan config:clear`.
5. **Recriar containers** — `docker compose up -d` após alterar `docker-compose.yml` / `Dockerfile`.

## Critério de conclusão

- `config('app.timezone')` retorna `America/Sao_Paulo`.
- Novo registro exibe `created_at` igual ao relógio local de Brasília.

## Riscos

- Dados históricos em UTC — mitigar com migração de correção se o negócio exigir retroativo.
