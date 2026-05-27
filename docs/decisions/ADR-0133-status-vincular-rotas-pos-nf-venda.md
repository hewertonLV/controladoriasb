# ADR-0133: Status «Vincular rotas nos pedidos» após NF de venda

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** [ADR-0132](ADR-0132-upload-nf-venda-finaliza-vendas.md), portão de rota em finalizar vendas

## Contexto

A validação de rota obrigatória para lojas com quantidade na matriz bloqueava o upload da NF em `FATURAMENTO_CIGAN_INICIADO`, impedindo efetivar movimentações de venda quando faltava rota em alguma loja.

## Decisão

- Inserir status `VINCULAR_ROTAS_NOS_PEDIDOS` («Vincular rotas nos pedidos») **depois** do upload da NF de venda.
- No upload: manter `GerarVendasCaptacaoLoteService` (movimentações) e **não** exigir rota; avançar para `VINCULAR_ROTAS_NOS_PEDIDOS`.
- Se, ao entrar nesse status, todas as lojas com quantidade já tiverem rota, avançar **automaticamente** para `VENDAS_FINALIZADAS`.
- Se houver pendência: operador vincula rotas na matriz e clica **Concluído** (pipeline) para validar rotas e ir a `VENDAS_FINALIZADAS`.
- Ao vincular rota na matriz, reavaliar auto-avanço quando o lote estiver em `VINCULAR_ROTAS_NOS_PEDIDOS`.
- A validação `assertPedidosComQuantidadeTemRota` deixa de rodar no upload e passa a valer só na conclusão da etapa de rotas.

## Alternativas consideradas

- Manter bloqueio no upload — rejeitado; impede movimentar estoque quando NF já foi emitida no Cigam.
- Exigir rota antes de iniciar faturamento — rejeitado; operação prefere liberar NF e rotas em sequência.

## Consequências

- [PLAN-0133](../plans/PLAN-0133-status-vincular-rotas-pos-nf-venda.md).
- Atualiza o fluxo descrito em ADR-0132 (finalização completa do ciclo exige rotas + conclusão ou auto-avanço).
