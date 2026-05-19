# PLAN-0017: CRUD admin de estados com inativação por soft delete

**ADR:** [ADR-0017](../decisions/ADR-0017-crud-estados-soft-delete.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Telas de cadastro, edição e inativar/reativar estados (UF) para ICMS.

## Passos

1. Permissões, controller, requests, views, rotas, menu.
2. Binding `withTrashed`; validação única ignorando soft deleted.
3. Testes de feature.

## Critério de conclusão

Usuário autorizado cadastra estado, edita, inativa sem vínculos e reativa; bloqueio com vínculos ativos.
