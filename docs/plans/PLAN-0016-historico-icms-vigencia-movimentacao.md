# PLAN-0016: Histórico operacional de ICMS e vigência na movimentação

**ADR:** [ADR-0016](../decisions/ADR-0016-historico-icms-vigencia-movimentacao.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Histórico de ICMS consultável por data e cálculo de movimentações usando vigência na `data_movimentacao`.

## Passos

1. Migration `fruta_icms_historicos` + backfill.
2. Model, `FrutaIcmsHistoricoService`, integrar em `FrutaIcmsSyncService` e `FrutaIcmsCalculoService`.
3. Passar data nas movimentações; UI de histórico na edição de ICMS.
4. Menu Logística com Fretes; testes.

## Critério de conclusão

Alterar ICMS gera linha em `fruta_icms_historicos`; compra antiga mantém `icms_convertido_kg` após mudança de cadastro; cálculo com data usa histórico vigente.
