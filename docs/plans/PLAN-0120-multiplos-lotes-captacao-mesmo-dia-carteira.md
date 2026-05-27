# PLAN-0120: Vários lotes captação mesmo dia × carteira

**ADR:** [ADR-0120](../decisions/ADR-0120-multiplos-lotes-captacao-mesmo-dia-carteira.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Permitir nova captação no mesmo dia e carteira quando não houver lote em andamento.

## Passos

1. Migration — drop unique `cap_lote_data_cart_uq`.
2. `CaptacaoLoteService` — recuperar em andamento ou criar novo.
3. Controller — mensagem recuperado vs criado.
4. Testes feature.

## Critério de conclusão

Segundo lote criado após finalizar o primeiro; bloqueio implícito via recuperação se já houver em andamento.
