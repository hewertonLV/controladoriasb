# PLAN-0054: APP_URL dinâmica sem IP fixo no código

**ADR:** [ADR-0054](../decisions/ADR-0054-app-url-dinamica-sem-ip-fixo.md)
**Data:** 2026-05-20
**Status:** Concluído

## Objetivo

Eliminar dependência de IP fixo em `APP_URL`, usando host da requisição ou detecção automática do servidor.

## Passos

1. Criar `App\Support\DynamicAppUrl` com resolução por requisição e fallback por IPv4 + `APP_PORT`.
2. Atualizar `UseRequestRootUrl` para aplicar a URL dinâmica a cada requisição web.
3. Registrar fallback no `AppServiceProvider` para CLI, filas e storage.
4. Manter `APP_URL=` vazio no `.env.example` e documentação.

## Critérios de aceite

- Nenhum IP de produção versionado no repositório.
- `route()` e `Storage::disk('public')->url()` usam o host acessado pelo navegador.
- Teste unitário cobre resolução com `APP_URL` vazio e preenchido.
