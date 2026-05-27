# PLAN-0094: Ordem de carregamento por rota na matriz

**ADR:** [ADR-0094](../decisions/ADR-0094-matriz-aba-ordem-carregamento-por-rota.md)
**Data:** 2026-05-24
**Status:** Concluído

## Objetivo

Aba Por rota com ordem de carregamento dinâmica por loja dentro de cada rota.

## Passos

1. Migration `ordem_carregamento` em `pedidos`.
2. Serviço de agrupamento por rota + reordenação no `PedidoService`.
3. Endpoint PATCH ordem + snapshot JSON.
4. UI aba + JS render dinâmico.
5. Testes.

## Critério de conclusão

- Ordem persiste e reordena linhas; testes verdes.

## Riscos

- Conflito ao editar duas ordens simultaneamente — mitigação: poll + version.
