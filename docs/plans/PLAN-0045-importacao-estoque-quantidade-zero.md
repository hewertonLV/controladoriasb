# PLAN-0045: Importação de estoque — saldo zero ou negativo

**ADR:** [ADR-0045](../decisions/ADR-0045-importacao-estoque-quantidade-zero.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Permitir importar posição com quantidade zero ou negativa na UM (e preço total coerente).

## Passos

1. `EstoqueImportacaoProcessor` e `EstoqueImportacaoPosicaoDerivador` aceitam negativos.
2. `EstoqueMovimentacaoService::definirPosicaoAbsoluta` sem bloqueio de qtd/preço negativos.
3. Textos da tela e testes.

## Critério de conclusão

- Planilha com `-2` na C e `0` na D importa saldo negativo.
- Planilha com `0` na C e valor ≠ 0 continua com erro.
