# PLAN-0102: Reconciliar lote quando faturamento/dia já finalizado

**ADR:** [ADR-0102](../decisions/ADR-0102-reconciliar-lote-faturamento-finalizado.md)
**Data:** 2026-05-24
**Status:** Concluído

## Objetivo

Eliminar travamento quando o dia de faturamento já foi finalizado mas o lote de galpão ficou em captação em andamento.

## Pré-requisitos

- ADR-0070 implementado.

## Passos

1. **Service** — `sincronizarStatusComFaturamentoFinalizado()` e batch por data/faturamento.
2. **Action** — finalizar idempotente quando dia já fechado.
3. **Controllers** — reconciliar ao abrir matriz e ver lote.
4. **Testes** — cenário dia finalizado + lote em andamento.

## Critério de conclusão

- Matriz/ver lote exibem status correto após abrir.
- Finalizar com dia já fechado avança lote sem erro.
- Testes passam.

## Riscos

- Sincronizar lote que ainda deveria estar aberto — mitigado: só quando registro do dia está finalizado.
