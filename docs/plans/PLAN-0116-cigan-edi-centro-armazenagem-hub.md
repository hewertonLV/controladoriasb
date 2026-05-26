# PLAN-0116: Centro de armazenagem no EDI Cigan

**ADR:** [ADR-0116](../decisions/ADR-0116-cigan-edi-centro-armazenagem-hub.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Preencher centro de armazenagem do HUB nas pos. 605–607 (N) e 659–661 (I) do TXT.

## Passos

1. `codigoCentroArmazenagemCigam()` no gerador.
2. Testes unit/feature nas posições.
3. Atualizar ADR-0105, ADR-0112.

## Critério de conclusão

TXT com centro `001` (ou cadastrado) após UN `120`; testes verdes.
