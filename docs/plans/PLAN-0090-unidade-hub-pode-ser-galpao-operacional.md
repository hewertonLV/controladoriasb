# PLAN-0090: Unidade HUB pode ser galpão operacional

**ADR:** [ADR-0090](../decisions/ADR-0090-unidade-hub-pode-ser-galpao-operacional.md)
**Data:** 2026-05-24
**Status:** Concluído

## Objetivo

Permitir cadastro e uso de unidade com HUB e galpão operacional ao mesmo tempo.

## Passos

1. Remover validação de exclusão mútua (form, importação, trait).
2. Ajustar listagem/validação de centro de resultado na venda.
3. Teste de cadastro com ambas as flags.

## Critério de conclusão

- `UnidadeNegocioTest` com HUB+galpão passa; regras de estoque e NF mantidas.

## Riscos

- HUB puro sem galpão ainda não é centro de resultado — comportamento preservado.
