# PLAN-0080: Alertas fruta habitual ausente no romaneio

**ADR:** [ADR-0080](../decisions/ADR-0080-alertas-fruta-habitual-ausente-romaneio.md)
**Data:** 2026-05-23
**Status:** Pendente

## Objetivo

Segunda aba em Alertas comerciais: por loja que já pediu hoje, listar frutas habituais (2/4 semanas, mesmo dia da semana) que ainda não estão no pedido/romaneio em montagem.

## Pré-requisitos

- [PLAN-0078](PLAN-0078-alertas-lojas-sem-pedido-dia-semana.md) ou módulo base de alertas com rotas/permissão.
- ≥ 4 semanas de itens de pedido.

## Passos

1. Query habitual cliente×produto (2/4, dia da semana).
2. Cruzar com itens do lote `CAPTACAO_EM_ANDAMENTO` (somente lojas com ≥ 1 item hoje).
3. Aba “Frutas faltantes” na tela de alertas + testes sintéticos.
4. (Opcional) badge na matriz de captação por loja com contagem de faltantes.

## Critério de conclusão

Loja pediu hoje só maçã; nas 3 últimas terças pediu maçã e banana → alerta `(loja, banana)`.

## Ordem

Junto com ou logo após PLAN-0078 (fase 2).
