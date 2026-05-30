# ADR-0163: Soft delete em demandas de captação

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Exclusão de demandas de transferência/venda da rota e reabertura de rota

## Contexto

Ao excluir uma demanda ou reabrir a rota, o registro sumia do banco (hard delete), impossibilitando auditoria do histórico operacional.

## Decisão

- **`captacao_lote_movimentacoes`** e **`captacao_lote_movimentacao_linhas`** passam a usar **soft delete** (`deleted_at`).
- Exclusão explícita da demanda, reabertura de rota e remoção de linhas obsoletas na sincronização chamam `$model->delete()` (soft), nunca `forceDelete()` em fluxo normal.
- Consultas operacionais continuam via Eloquent padrão (ignoram registros excluídos).
- Ao excluir o cabeçalho da demanda, as linhas filhas também recebem soft delete em cascata.

## Alternativas consideradas

- **Manter hard delete** — rejeitada; perde rastreabilidade.
- **Tabela de histórico separada** — rejeitada; soft delete já é padrão do projeto (`VendaNota`, etc.).

## Consequências

- [PLAN-0163](../plans/PLAN-0163-soft-delete-demandas-captacao.md).
- Testes de reabertura de rota usam `assertSoftDeleted` em vez de `assertDatabaseMissing`.
