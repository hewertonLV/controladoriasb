# PLAN-0112: Espécie estoque «S» no EDI Cigan

**ADR:** [ADR-0112](../decisions/ADR-0112-cigan-edi-especie-estoque-saida.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Preencher Espécie estoque com «S» nos registros N e I do TXT.

## Passos

1. `especieEstoqueCigam()` + config.
2. Gravar pos. 608 (N) e 659 (I).
3. Testes pipeline e unitários.

## Critério de conclusão

TXT com «S» nas posições; testes verdes.
