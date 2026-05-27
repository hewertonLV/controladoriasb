# ADR-0133: Status «Vincular rotas nos pedidos» após NF de venda

**Data:** 2026-05-26
**Status:** Aceito
**Contexto:** [ADR-0132](ADR-0132-upload-nf-venda-finaliza-vendas.md), portão de rota em finalizar vendas

## Contexto

A validação de rota obrigatória para lojas com quantidade na matriz bloqueava o upload da NF em `FATURAMENTO_CIGAN_INICIADO`, impedindo efetivar movimentações de venda quando faltava rota em alguma loja.

## Decisão

- Inserir status `VINCULAR_ROTAS_NOS_PEDIDOS` («Vincular rotas nos pedidos») **depois** do upload da NF de venda.
- No upload: manter `GerarVendasCaptacaoLoteService` (movimentações) e **não** exigir rota; avançar para `VINCULAR_ROTAS_NOS_PEDIDOS`.
- O avanço para `VENDAS_FINALIZADAS` **não é automático**; o operador clica **Concluir rotas e carregamento** no pipeline.
- Na conclusão, validar rota (`assertPedidosComQuantidadeTemRota`) e ordem de carregamento (`assertPedidosComQuantidadeTemOrdemCarregamento`) para cada loja com quantidade.
- Rotas na aba **Rotas** (salvamento automático ao selecionar); ordem na aba **Por rota**.
- Sem rota ou sem ordem: o botão de conclusão retorna erro e o status não muda.

## Alternativas consideradas

- Manter bloqueio no upload — rejeitado; impede movimentar estoque quando NF já foi emitida no Cigam.
- Exigir rota antes de iniciar faturamento — rejeitado; operação prefere liberar NF e rotas em sequência.

## Consequências

- [PLAN-0133](../plans/PLAN-0133-status-vincular-rotas-pos-nf-venda.md).
- Atualiza o fluxo descrito em ADR-0132 (finalização completa do ciclo exige rotas + conclusão ou auto-avanço).
