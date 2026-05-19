# PLAN-0010: Log temporário de requisições (mesmo da ADR)

**ADR:** [ADR-0010](../decisions/ADR-0010-log-temporario-requisicoes.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Permitir diagnosticar requisições lentas lendo o último registro em `storage/logs/request-debug.jsonl`.

## Pré-requisitos

- `.env` com `REQUEST_DEBUG_ENABLED=true`

## Passos

1. **Ativar** — `REQUEST_DEBUG_ENABLED=true` no `.env` local.
2. **Reproduzir** — abrir a tela/rota lenta no navegador.
3. **Analisar** — `tail -1 storage/logs/request-debug.jsonl` ou pedir ao assistente para analisar o último registro.
4. **Desativar** — `REQUEST_DEBUG_ENABLED=false` quando não precisar mais.
5. **Remover** — apagar middleware, `config/request_debug.php`, `RequestDebugLogger` e esta ADR/PLAN quando concluído o diagnóstico.

## Critério de conclusão

Última linha do JSONL contém `duration_ms`, `timeline`, `likely_causes`, `database.slowest` e permite identificar gargalo (query N+1, rota específica, tema em refresh, etc.).

## Riscos

- Arquivo cresce em uso intenso — apagar `request-debug.jsonl` periodicamente em dev.
- Query log adiciona overhead mínimo — aceitável só em diagnóstico.
