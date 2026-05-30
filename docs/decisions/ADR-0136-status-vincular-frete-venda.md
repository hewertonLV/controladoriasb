# ADR-0136: Status «Vincular frete venda» antes de vendas finalizadas

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** [ADR-0125](ADR-0125-frete-vendas-por-loja-pos-finalizacao.md), [ADR-0133](ADR-0133-status-vincular-rotas-pos-nf-venda.md)

## Contexto

O frete de vendas por loja estava na aba visível só em `VENDAS_FINALIZADAS`, misturando conclusão do ciclo com vínculo opcional de frete. A operação pediu etapa dedicada antes do status final.

## Decisão

- Inserir `VINCULAR_FRETE_VENDA` («Vincular frete venda») **entre** `VINCULAR_ROTAS_NOS_PEDIDOS` e `VENDAS_FINALIZADAS`.
- Ao concluir rotas: avançar para `VINCULAR_FRETE_VENDA` (não para vendas finalizadas).
- Aba **Frete Vendas** visível em `VINCULAR_FRETE_VENDA` e permanece em `VENDAS_FINALIZADAS` para consulta/ajuste.
- Frete **opcional** por loja; botão **Concluir frete venda** avança para `VENDAS_FINALIZADAS` sem exigir frete.
- Na aba: lojas com saída **HUB** primeiro, borda distinta; saída **galpão** em seguida; cards responsivos (até 3 por linha em telas largas).

## Alternativas consideradas

- Manter frete só no status final — rejeitado; operação quer etapa explícita no pipeline.
- Exigir frete para concluir — rejeitado; frete continua opcional.

## Consequências

- [PLAN-0136](../plans/PLAN-0136-status-vincular-frete-venda.md).
- Atualizar ADR-0125 (aba também em `VINCULAR_FRETE_VENDA`).
