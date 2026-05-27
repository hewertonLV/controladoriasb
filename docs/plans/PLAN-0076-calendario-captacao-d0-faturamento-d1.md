# PLAN-0076: Calendário captação D0 / faturamento D+1

**ADR:** [ADR-0076](../decisions/ADR-0076-calendario-captacao-d0-faturamento-d1.md)
**Data:** 2026-05-23
**Status:** Pendente

## Objetivo

Suportar captação no dia D, faturamento Jefferson em D+1 ou na data de saída prevista.

## Passos

1. Campos `data_captacao`, `data_saida_prevista` no pedido/lote.
2. Fila Jefferson filtrada por pendente faturamento e data de saída.
3. Documentar na UI expectativa D / D+1.
4. Testes: preço alterado entre D e Finalizar venda usa valor vigente.

## Critério de conclusão

Pedidos captados hoje aparecem para faturamento amanhã ou na data de saída configurada.
