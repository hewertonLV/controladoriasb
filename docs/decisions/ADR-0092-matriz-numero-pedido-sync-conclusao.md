# ADR-0092: Número do pedido na matriz e sync de conclusão

**Data:** 2026-05-24
**Status:** Aceito
**Contexto:** Matriz de captação por loja

## Contexto

Operação precisa informar o número do pedido da loja abaixo do nome na matriz. O botão «Concluir» por linha não refletia mudanças de outros usuários sem recarregar a página.

## Decisão

- Campo `pedidos.numero_pedido` (string nullable, até 60 caracteres), editável na coluna da loja na matriz.
- Snapshot JSON da matriz (`/matriz/estado`) inclui `pedidos[id_cliente]` com `captacao_concluida` e `numero_pedido`; versão do poll considera `updated_at` dos pedidos.
- Ao concluir/reabrir via AJAX, a UI atualiza a linha localmente (sem reload); o poll sincroniza entre abas/usuários.

## Alternativas consideradas

- **Número por item** — rejeitado; é identificador do pedido da loja, não da fruta.
- **Reload após concluir** — rejeitado; quebra fluxo e não resolve sync multi-usuário.

## Consequências

- [PLAN-0092](../plans/PLAN-0092-matriz-numero-pedido-sync-conclusao.md).
