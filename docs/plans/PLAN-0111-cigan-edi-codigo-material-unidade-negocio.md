# PLAN-0111: Código material e unidade negócio no EDI Cigan

**ADR:** [ADR-0111](../decisions/ADR-0111-cigan-edi-codigo-material-unidade-negocio.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Formatar código material com 6 dígitos + espaços e unidade negócio com id_cigam do HUB de origem.

## Passos

1. Ajustar `codigoMaterialCigam` e `montarRegistroItem` (`colocarExato`).
2. Repassar código da UN de faturamento ao registro `I`.
3. Testes unitários e de pipeline.

## Critério de conclusão

TXT com material `              000042` (ex.) e UN `003` para HUB `883003`; testes verdes.
