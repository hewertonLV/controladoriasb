# PLAN-0166: Status CAPTACAO_CONCLUIDA

**ADR:** [ADR-0166](../decisions/ADR-0166-captacao-lote-status-concluida.md)
**Data:** 2026-05-28
**Status:** Concluído

## Objetivo

Encerrar captação do lote em `CAPTACAO_CONCLUIDA` com botão visível no fluxo pedidos-por-loja/matriz, sem avançar ao pipeline Cigam legado.

## Pré-requisitos

- ADR-0165 (rotas concluídas no fechamento do lote).

## Passos

1. **Enum** — `CaptacaoConcluida` e flags de edição.
2. **Service/Action** — `ConcluirCaptacaoLoteService` + validações.
3. **HTTP** — rota POST, controllers pedidos-por-loja e matriz.
4. **UI** — botão em `pedidos-por-loja/lojas` e cabeçalho matriz.
5. **Faturamento/sync** — `FinalizarCaptacaoFaturamento` e ADR-0102 → `CAPTACAO_CONCLUIDA`.
6. **PedidoService** — remover bloqueio de rota ao finalizar loja.
7. **Testes** — feature pedidos-por-loja e matriz.

## Critério de conclusão

Botão visível; POST conclui lote; status não volta a pipeline antigo ao abrir matriz; testes verdes.

## Riscos

- Lotes já em status intermediário — não migrados automaticamente; só novos fechamentos.
