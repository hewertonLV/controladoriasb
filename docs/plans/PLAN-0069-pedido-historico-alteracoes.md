# PLAN-0069: Histórico de pedido e item

**ADR:** [ADR-0069](../decisions/ADR-0069-pedido-historico-alteracoes.md)
**Data:** 2026-05-23
**Status:** Pendente

## Objetivo

Garantir registro append-only de toda alteração em pedido/item com origem APP/WEB e consulta no app e na web.

## Pré-requisitos

- Modelo `Pedido` / `PedidoItem` do PLAN-0066.

## Passos

1. Migrations `pedido_historicos` e `pedido_item_historicos`.
2. `PedidoAuditoriaService` com snapshot e diff (`alteracoes`).
3. Integrar em API app, endpoint célula da matriz e cancelamentos.
4. API `GET pedidos/{id}/historico` e `GET pedido-itens/{id}/historico`.
5. App: tela listagem + detalhe do histórico do captador.
6. Web: drawer opcional “histórico da célula”.
7. Testes Feature cobrindo create/update com `origem` APP e WEB.

## Critério de conclusão

- Nenhuma mutação de pedido/item sem linha de histórico correspondente.

## Riscos

- Payload grande em `dados_antes/depois` — gravar só campos relevantes do item.

## Ordem

Executar como passo 1 do [PLAN-0068](PLAN-0068-api-pedidos-painel-tempo-real.md) (incorporado lá); este plano documenta o escopo isolado da ADR-0069.
