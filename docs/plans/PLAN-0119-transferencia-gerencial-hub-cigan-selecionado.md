# PLAN-0119: Transferência gerencial com HUB Cigan selecionado

**ADR:** [ADR-0119](../decisions/ADR-0119-transferencia-gerencial-hub-cigan-selecionado.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Garantir que o upload da NF crie transferências HUB (selecionado) → galpão do lote.

## Passos

1. Ajustar `EfetivarTransferenciasGerenciaisLoteService::resolverUnidadeOrigem`.
2. Exigir HUB salvo para captação pedidos.
3. Testes de integração com dois HUBs.

## Critério de conclusão

Movimentação de saída usa empresa do HUB definido em `id_unidade_negocio_hub_origem`.
