# PLAN-0092: Número do pedido na matriz e sync de conclusão

**ADR:** [ADR-0092](../decisions/ADR-0092-matriz-numero-pedido-sync-conclusao.md)
**Data:** 2026-05-24
**Status:** Concluído

## Objetivo

Campo número do pedido na matriz e sincronização em tempo real do estado de conclusão por loja.

## Passos

1. Migration `numero_pedido` em `pedidos`.
2. Endpoint PATCH + serviço para gravar número.
3. Estender snapshot da matriz com estado dos pedidos.
4. UI + JS: input, sync poll, conclusão sem reload.
5. Testes em `CaptacaoMatrizTest`.

## Critério de conclusão

- Campo visível e persistido; conclusão sincroniza via poll e após clique local.

## Riscos

- Conflito de edição simultânea do número — mitigação: poll sobrescreve só se campo não focado.
