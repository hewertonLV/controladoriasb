# PLAN-0163: Soft delete em demandas de captação

**ADR:** [ADR-0163](../decisions/ADR-0163-soft-delete-demandas-captacao.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Preservar no banco demandas e linhas excluídas, usando `deleted_at`.

## Passos

1. Migration `deleted_at` em `captacao_lote_movimentacoes` e `captacao_lote_movimentacao_linhas`.
2. Trait `SoftDeletes` nos models + cascata ao excluir cabeçalho.
3. Serviços de exclusão/reabertura/sincronização usam `delete()` do model.
4. Sincronização de linhas restaura registro soft-deleted quando a fruta volta.
5. Ajustar testes para `assertSoftDeleted`.

## Critério de conclusão

- Reabrir rota mantém registros com `deleted_at` preenchido.
- Excluir demanda de transferência não remove linha do banco.

## Riscos

- `updateOrCreate` duplicar linha — mitigado com `withTrashed()` + `restore()`.
