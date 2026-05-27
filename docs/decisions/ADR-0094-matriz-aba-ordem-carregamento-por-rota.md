# ADR-0094: Ordem de carregamento por rota na matriz

**Data:** 2026-05-24
**Status:** Aceito
**Contexto:** Matriz de captação — aba Por rota

## Contexto

Após vincular lojas às rotas, a operação precisa definir a **sequência de carregamento** dentro de cada rota.

## Decisão

- Campo `pedidos.ordem_carregamento` (inteiro nullable), **por loja/pedido**, escopo da rota do pedido.
- Aba **Por rota**: colunas Rota | Ordem | Loja | Item | Qtd (UM); só lojas **com rota** e quantidade > 0.
- Ao escolher ordem N, o sistema **reordena** as demais lojas da mesma rota (1..n contíguo).
- Ao trocar/remover rota, `ordem_carregamento` é limpa.

## Alternativas consideradas

- **Ordem por item** — rejeitado; carregamento é por parada/loja.
- **Ordem global do lote** — rejeitado; sequência é por rota.

## Consequências

- [PLAN-0094](../plans/PLAN-0094-matriz-aba-ordem-carregamento-por-rota.md).
