# PLAN-0114: Código material Cigan só das frutas do lote

**ADR:** [ADR-0114](../decisions/ADR-0114-cigan-edi-material-fruta-do-lote.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Garantir que o campo material (pos. 3–22) do TXT use o `id_cigam` das frutas dos pedidos do lote baixado.

## Passos

1. Romaneio: query explícita de pedidos por `id_captacao_lote`; expor `id_cigam` na linha.
2. Gerador: recarregar lote; usar `id_cigam` do romaneio; remover `Fruta::whereIn`.
3. Teste de isolamento entre dois lotes com frutas diferentes.

## Critério de conclusão

- TXT do lote B não contém código material do lote A; testes verdes.
