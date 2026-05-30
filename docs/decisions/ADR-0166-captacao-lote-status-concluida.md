# ADR-0166: Status CAPTACAO_CONCLUIDA no fluxo por loja/rota

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Módulo pedidos-por-loja e matriz por rota (ADR-0148, ADR-0165).

## Contexto

O pipeline legado (`AGUARDANDO_TRANSFERENCIA_CIGAN` → … → `VENDAS_FINALIZADAS`) não corresponde ao fluxo operacional atual: captação por loja, rotas na matriz e demandas nos módulos Transferências/Vendas. O botão «Concluir captação» sumiu da matriz com a remoção da timeline; vendedores precisam encerrar o lote em `CAPTACAO_CONCLUIDA`.

## Decisão

- Novo status de lote: **`CAPTACAO_CONCLUIDA`** — fim da fase de captação (quantidades, rotas por loja, finalização de pedidos).
- **Ordem operacional:** (1) `captacao_concluida` em cada loja com quantidade; (2) concluir cada rota ([ADR-0152](ADR-0152-rota-concluida-matriz-por-rota.md)); (3) **Concluir captação do lote**.
- **Concluir captação do lote** transiciona de `CAPTACAO_EM_ANDAMENTO` para `CAPTACAO_CONCLUIDA` quando todas as rotas com pedido estão concluídas e todas as lojas com quantidade têm `captacao_concluida`.
- **Finalizar captação do faturamento** e reconciliação ADR-0102 usam `CAPTACAO_CONCLUIDA` em vez de `AGUARDANDO_TRANSFERENCIA_CIGAN` para lotes `CAPTACAO_PEDIDOS`.
- **Finalizar pedido da loja** não exige rota concluída (só quantidade); **reabrir** loja exige rota aberta ([ADR-0152](ADR-0152-rota-concluida-matriz-por-rota.md)).
- Após `CAPTACAO_CONCLUIDA`, edição de quantidades, preços e vínculo de rota fica bloqueada; demandas/transferências/vendas seguem nos módulos já gerados.

## Alternativas consideradas

- Manter pipeline Cigam após captação — rejeitado para o fluxo vendedor/matriz atual.
- Reutilizar `VENDAS_FINALIZADAS` como «captação feita» — rejeitado: semântica incorreta e mistura etapas.
- Concluir lote só com rotas, sem lojas finalizadas — rejeitado: lojas sem `captacao_concluida` indicam captação incompleta.

## Consequências

- Status intermediários do enum permanecem para lotes/romaneios legados e telas administrativas que ainda os referenciam.
- UI de pedidos-por-loja exibe botão «Concluir captação» com pendências listadas quando bloqueado.
