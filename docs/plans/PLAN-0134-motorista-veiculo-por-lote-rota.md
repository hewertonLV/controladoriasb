# PLAN-0134: Motorista e veículo por lote e rota

**ADR:** [ADR-0134](../decisions/ADR-0134-motorista-veiculo-por-lote-rota.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Isolar motorista e veículo por captação (lote), sem propagar alterações entre lotes da mesma carteira.

## Passos

1. Migration `captacao_lote_rotas` + backfill.
2. Model `CaptacaoLoteRota` e refatorar `CaptacaoMatrizRotasService`.
3. Ajustar `CaptacaoMatrizEstadoService` (version/poll).
4. Testes de matriz (veículo, motorista, exclusividade no lote).

## Critério de conclusão

Alterar veículo/motorista na rota A do lote 1 não altera rota A do lote 2; exclusividade de veículo só dentro do mesmo lote.
