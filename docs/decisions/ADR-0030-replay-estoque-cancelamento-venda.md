# ADR-0030: Replay de estoque ao cancelar venda

**Data:** 2026-05-19
**Status:** Aceito
**Contexto:** Cancelamento administrativo de venda não restituía quantidade correta no estoque

## Contexto

O cancelamento marcava a venda como `CANCELADO` e executava replay integral da linha do tempo. Quando havia compra (ou outra entrada) **depois** da venda, o replay ignorava a saída cancelada mas somava entradas posteriores sobre o saldo **antes** da venda, inflando o estoque (ex.: 100 − 20 + 30 virava 130 em vez de 110).

## Decisão

1. Remover estorno manual (`estornarVendaNoEstoqueOrigem`) no cancelamento — o replay é a única fonte de verdade.
2. Ao cancelar, chamar replay **a partir da movimentação cancelada** (`movimentacaoInicioId`).
3. Regras de baseline:
   - Sem eventos posteriores ativos → replay integral (como compra cancelada).
   - Só entradas posteriores → baseline = `id_movimentacao_estoque_new` da venda (saldo após a saída), reaplicar entradas posteriores.
   - Há saídas posteriores → baseline = `id_movimentacao_estoque_old` (antes da venda), reaplicar posteriores.
4. Baseline “exato” por id não percorre cadeia de movimentação cancelada (evita voltar ao saldo pré-venda por engano).

## Alternativas consideradas

- Manter estorno manual + replay integral — rejeitado por dupla contagem ou inflação.
- Não fazer replay quando há entrada posterior — rejeitado; quebra cenário venda + venda posterior.

## Consequências

- Estoque e `movimentacao_estoques` permanecem consistentes após cancelamento em qualquer posição da linha do tempo.
- `estornarVendaNoEstoqueOrigem` permanece no código para eventual uso pontual, mas não é usado no fluxo de cancelamento admin.
