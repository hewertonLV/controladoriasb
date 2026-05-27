# ADR-0113: Quantidade EDI Cigan com máscara N8.6

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** Campo 003 do registro `I` (pos. 24–38) no TXT de transferência ([ADR-0105](ADR-0105-arquivo-cigan-edi-transferencia-hub.md))

## Contexto

O manual EDI NF Cigan define o campo **003 Quantidade** com máscara **N8.6** (15 posições: parte inteira + **6 dígitos** de casas decimais implícitas). Ex.: 5 UM → `000000005000000`; 1.060 → `000001060000000`.

## Decisão

- Campo **003 Quantidade** (pos. 24–38): **N8.6** — `round(a_receber_um × 1.000.000)`, 15 dígitos com zeros à esquerda.
- Exemplos: 5 → `000000005000000`; 100 → `000000100000000`; 1.060 → `000001060000000`; 62,5 → `000000062500000`.
- Campo **004 Peças** (pos. 40–53): permanece em branco; quantidade só no 003.

## Alternativas consideradas

- Inteiro de 15 posições sem × 1.000.000 — rejeitado pela operação: layout oficial e arquivos de referência usam os 6 zeros decimais.
- Escalar × 100 (2 decimais) — rejeitado: máscara do manual é N8.6.

## Consequências

- [PLAN-0113](../plans/PLAN-0113-cigan-edi-quantidade-sem-n86.md).
- Atualizar redação de quantidade em [ADR-0105](ADR-0105-arquivo-cigan-edi-transferencia-hub.md).
