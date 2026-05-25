# ADR-0102: Reconciliar lote quando faturamento/dia já finalizado

**Data:** 2026-05-24
**Status:** Aceito
**Contexto:** Finalização da captação ([ADR-0070](ADR-0070-finalizar-captacao-unidade-faturamento.md))

## Contexto

Em casos raros, `captacao_faturamento_dias` ficava com status `CAPTACAO_FATURAMENTO_FINALIZADA` enquanto algum lote de galpão permanecia em `CAPTACAO_EM_ANDAMENTO`. A UI mostrava «Finalizar captação» e, ao clicar, retornava «Captação já finalizada para esta data e faturamento» sem avançar o lote.

## Decisão

- Se o faturamento/dia já estiver finalizado e existirem lotes `CAPTACAO_PEDIDOS` ainda em `CAPTACAO_EM_ANDAMENTO` na mesma data e faturamento, **sincronizar** esses lotes para `AGUARDANDO_TRANSFERENCIA_CIGAN` em vez de erro.
- Reconciliação automática ao abrir **matriz** ou **ver lote**.
- Nova tentativa de **Finalizar captação** com dia já fechado executa a sincronização e conclui com sucesso.

## Alternativas consideradas

- Reabrir o faturamento/dia — rejeitado; MVP não prevê reabertura ([ADR-0070](ADR-0070-finalizar-captacao-unidade-faturamento.md)).
- Manter erro e correção manual no banco — rejeitado; operação ficaria travada.

## Consequências

- Estado inconsistente é corrigido sem intervenção manual.
- [PLAN-0102](../plans/PLAN-0102-reconciliar-lote-faturamento-finalizado.md).
