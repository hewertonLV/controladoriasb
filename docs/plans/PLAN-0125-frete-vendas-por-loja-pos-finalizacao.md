# PLAN-0125: Frete de vendas por loja após vendas finalizadas

**ADR:** [ADR-0125](../decisions/ADR-0125-frete-vendas-por-loja-pos-finalizacao.md)
**Data:** 2026-05-26
**Status:** Concluído

## Objetivo

Duas abas na matriz (Frete HUB x CD e Frete Vendas) com vínculo opcional de frete por loja após vendas finalizadas.

## Pré-requisitos

- Vendas do lote geradas (`captacao_lote_movimentacoes` tipo `VENDA_NOTA`).
- Permissão `captacao.lote.frete.vincular`.

## Passos

1. **Status e abas** — `exibeAbaFreteHub`, `exibeAbaFreteVendas`; slugs `frete-hub` e `frete-vendas`.
2. **Serviço** — `dadosFreteHub`, `dadosFreteVendas`, `vincularFreteVendaLoja`.
3. **VendaMovimentacaoService** — `vincularFreteNotaCaptacao` com recálculo de rateio.
4. **HTTP** — rota POST venda-loja; redirects e matriz controller.
5. **Views** — `_frete-hub-lote`, `_frete-vendas-lote`; tabs e ações do lote.
6. **Testes** — matriz, redirect, vínculo por loja.

## Critério de conclusão

Abas renomeadas/novas funcionando; POST por loja persiste frete nas movimentações; testes PHPUnit verdes.

## Riscos

- NF de captação ausente para loja sem venda — mitigar listando só pedidos com venda gerada.
