# PLAN-0110: Preço unitário em branco no EDI Cigan

**ADR:** [ADR-0110](../decisions/ADR-0110-cigan-edi-preco-unitario-em-branco.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Corrigir pos. 56–70 do registro I para 15 espaços no TXT de transferência.

## Passos

1. `precoUnitarioEmBrancoCigam()` e uso em `montarRegistroItem`.
2. Remover validação de preço na geração do arquivo.
3. Ajustar testes de pipeline e unitários.

## Critério de conclusão

Download do TXT com preço em branco; testes passam.
