# ADR-0069: Histórico obrigatório de alterações em pedido e item

**Data:** 2026-05-23
**Status:** Aceito
**Contexto:** Captação app + matriz web ([ADR-0068](ADR-0068-api-pedidos-painel-matriz-tempo-real.md))

## Contexto

Pedidos e itens podem ser criados e alterados pelo **app** e pela **web** (matriz). A operação e a auditoria exigem saber **quem** mudou **o quê**, **quando** e **de qual canal**, inclusive para consulta do histórico no aplicativo do captador.

## Decisão

- Tabelas `pedido_historicos` e `pedido_item_historicos` (ou histórico unificado em `pedido_item_historicos` com referência ao pedido), no padrão de `cliente_historicos` / `movimentacao_historicos`.
- **Toda** criação, atualização e cancelamento de pedido ou item dispara registro de histórico em transação com a mutação.
- Campos do registro: `pedido_id` / `pedido_item_id`, `user_id` (nullable se job sistema), `origem` (`APP`, `WEB`, `SISTEMA`), `acao` (`CRIACAO`, `ATUALIZACAO`, `CANCELAMENTO`), `dados_antes`, `dados_depois`, `alteracoes` (diff JSON), `created_at`.
- App: tela de **histórico** listando lançamentos do usuário/dispositivo com link para detalhe das alterações (somente leitura ou edição conforme travas).
- Web: opcional painel lateral “histórico da célula” (últimas N alterações do item).
- Histórico **append-only**; sem exclusão física.

## Alternativas consideradas

- **Log apenas em arquivo** — rejeitado; não consultável na UI.
- **Só auditoria no pedido cabeçalho** — rejeitado; mudança de quantidade/preço é no item.
- **Histórico só na web** — rejeitado; app também altera.

## Consequências

- `PedidoAuditoriaService` (ou equivalente) chamado em API e autosave da matriz.
- [PLAN-0068](PLAN-0068-api-pedidos-painel-tempo-real.md) inclui migration e testes de histórico.
- Volume de registros alto em dia movimentado — índice em `pedido_item_id`, `created_at`.
