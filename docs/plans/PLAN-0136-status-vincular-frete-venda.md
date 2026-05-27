# PLAN-0136: Status vincular frete venda

**ADR:** [ADR-0136](../decisions/ADR-0136-status-vincular-frete-venda.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Etapa de frete de vendas no pipeline antes de vendas finalizadas, com UI ordenada HUB/galpão.

## Passos

1. Enum `VincularFreteVenda` + timeline/pipeline.
2. Serviço e rota `concluir-frete-venda`.
3. `dadosFreteVendas` com ordenação e metadados de saída.
4. Blade `_frete-vendas-lote` em grid responsivo.
5. Testes de pipeline e integração.

## Critério de conclusão

Fluxo: rotas → vincular frete venda → vendas finalizadas; UI HUB primeiro com bordas distintas.
