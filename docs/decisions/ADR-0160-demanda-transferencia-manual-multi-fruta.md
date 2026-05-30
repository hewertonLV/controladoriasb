# ADR-0160: Demanda manual de transferência multi-fruta

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Falta de estoque no galpão de faturamento ao efetivar venda da rota ([ADR-0159](ADR-0159-venda-rota-desacoplada-transferencia.md))

## Contexto

Quando o estoque do galpão de faturamento não cobre o pedido da rota, o operador precisa abastecer via transferência criada manualmente no módulo Transferências, com várias frutas e sem informar preço.

## Decisão

- **Criação:** no módulo **Transferências**, ação **Nova demanda manual** (ou equivalente). Status inicial **`DEMANDA_CRIADA`** (distinto de `ABERTO` das demandas automáticas da captação).
- **Campos:** origem e destino (unidades de negócio); **várias linhas** `(fruta, quantidade UM)`; usuário **não** informa preço. Observação/descrição padrão indicando criação **manual** (ex.: «Demanda criada manualmente»), editável na fase `DEMANDA_CRIADA`.
- **Salvar:** persiste rascunho em `DEMANDA_CRIADA`; permite editar origem, destino e linhas.
- **Iniciar transferência:** botão separado; exige confirmação informando que, após iniciar, **não** será possível alterar dados — apenas excluir ou avançar no fluxo. Transição `DEMANDA_CRIADA` → `INICIADO` com mesma regra de estoque da [ADR-0158](ADR-0158-ciclo-demanda-transferencia-captacao.md) (saldo na origem, detalhamento do que falta).
- **Fase `INICIADO`:** arquivo Cigam, download, anexo NF → **`VINCULAR_FRETE`** ([ADR-0161](ADR-0161-vincular-frete-demanda-manual.md)).
- **Fase `VINCULAR_FRETE`:** vincular frete ABERTO existente **ou** confirmar **Sem frete** → **`CONCLUIDO`** → movimentação SB.
- **Exclusão:** permitida em `DEMANDA_CRIADA` e `INICIADO` (antes de `VINCULAR_FRETE`), conforme regras do módulo.
- **Vínculo com captação:** **opcional** (referência lote/rota em observação ou FK futura); **sem** bloqueio automático da venda da rota.

## Alternativas consideradas

- **Reutilizar demanda `ABERTO` da captação para manual** — rejeitado; origem e ciclo de edição são diferentes.
- **Transferência imediata sem demanda** — rejeitado; operação exige fluxo Cigam/NF.

## Consequências

- [PLAN-0160](../plans/PLAN-0160-demanda-transferencia-manual-multi-fruta.md).
- Nova entidade ou extensão de `captacao_lote_movimentacoes` / tabela `transferencia_demandas` com flag `origem = MANUAL`.
- Enum de status estendido: `DEMANDA_CRIADA` → `INICIADO` → `VINCULAR_FRETE` → `CONCLUIDO` (ver [ADR-0161](ADR-0161-vincular-frete-demanda-manual.md)).
