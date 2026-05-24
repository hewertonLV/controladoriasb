# PLAN-0075: Transferência gerencial Lucas e escopo por UN

**ADR:** [ADR-0075](../decisions/ADR-0075-transferencia-gerencial-lucas-escopo-unidade.md)
**Data:** 2026-05-23
**Status:** Pendente

## Objetivo

Validar transferências cria movimentações automáticas; Lucas vê só romaneios das UN de faturamento vinculadas.

## Passos

1. Filtro listagem Lucas por `usuario_unidade_faturamento` + permissão.
2. Garantir `EfetivarTransferenciasGerenciaisLote` idempotente e vinculado ao romaneio.
3. Testes de escopo e criação de transferência HUB→galpão.

## Critério de conclusão

Lucas valida → transferências gerenciais existem; usuário sem vínculo não vê lote alheio.
