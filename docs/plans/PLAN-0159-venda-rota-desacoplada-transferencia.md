# PLAN-0159: Venda da rota desacoplada da transferência

**ADR:** [ADR-0159](../decisions/ADR-0159-venda-rota-desacoplada-transferencia.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Permitir efetivar vendas da rota manualmente, com checagem de estoque e alerta de faltas no galpão, sem vínculo com transferência.

## Pré-requisitos

- [PLAN-0157](PLAN-0157-demandas-rota-sem-movimentacao-imediata.md) concluído.

## Passos

1. **Remover bloqueio** — eliminar `AGUARDANDO_TRANSFERENCIA` e `id_transferencia_origem_bloqueio` do fluxo de venda por rota captação.
2. **Ação efetivar venda** — endpoint + botão por demanda de venda; validar estoque na saída física efetiva; se insuficiente (galpão faturamento), retornar lista de faltas por fruta/qtd sem criar transferência.
3. **Saída HUB** — ao concluir venda, debitar origem HUB com CO faturamento embutido ([ADR-0135](ADR-0135-venda-hub-co-faturamento-embutido-custo-saida.md)).
4. **UI** — modal/toast com faltas; link opcional para criar demanda manual ([PLAN-0160](PLAN-0160-demanda-transferencia-manual-multi-fruta.md)).
5. **Testes** — venda concluída sem transferência concluída; falta estoque galpão retorna mensagem acionável.

## Critério de conclusão

- Usuário efetiva venda independente da transferência; faltas exibidas claramente.

## Riscos

- Venda HUB com transferência fiscal pendente — operação aceita desacoplamento explícito na ADR.
