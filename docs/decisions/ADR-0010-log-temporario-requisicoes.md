# ADR-0010: Log temporário de requisições para diagnóstico de lentidão

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Requisições web demorando sem causa evidente; necessidade de inspecionar o último registro com o assistente.

## Contexto

Não havia rastreio estruturado por requisição (tempo total, queries SQL, rota, usuário). Telescope/Clockwork não estavam no projeto.

## Decisão

Middlewares `StartRequestDebug` + `FinishRequestDebug` gravam **uma linha JSON por requisição** em `storage/logs/request-debug.jsonl`, controlado por `REQUEST_DEBUG_ENABLED` no `.env`. Cada registro inclui duração, rota, memória, **timeline** de marcos (`milestones`), resumo de queries, **`likely_causes`** (heurísticas automáticas) e dados de request/response sem campos sensíveis.

Requisições com `duration_ms >= REQUEST_DEBUG_SLOW_MS` recebem `"slow": true`. O middleware de tema registra `theme_session_hit` vs `theme_user_refresh` e só faz `refresh()` quando a sessão não tem tema do usuário atual.

Com `REQUEST_DEBUG_CLIENT_TRACKING=true`, o script `request-debug-client.js` grava o instante do clique, envia cookie `rd_trace` para correlacionar com o registro `server`, e após `window.load` POSTa métricas da Navigation Timing API. O backend grava registros `client` e `e2e` (mesclado) com breakdown por fase: clique→fetch, TTFB, download, DOM, assets.

## Alternativas consideradas

- **Laravel Telescope** — rejeitado: dependência pesada e persistência em DB para uso temporário.
- **Log só requisições lentas** — rejeitado: o “último registro” poderia não ser a requisição que o usuário acabou de abrir se outra requisição rápida ocorrer depois.
- **Canal Monolog padrão** — rejeitado: formato com prefixo dificulta parse automático; JSONL direto é mais simples para análise.

## Consequências

- Remover middleware, config e ADR quando o diagnóstico terminar.
- Ativar apenas em local/staging; não commitar o arquivo `.jsonl`.
- Inputs com senha/token são omitidos; SQL truncado em 400 caracteres.
