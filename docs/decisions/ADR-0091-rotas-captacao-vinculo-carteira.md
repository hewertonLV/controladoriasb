# ADR-0091: Rotas de captação vinculadas à carteira

**Data:** 2026-05-24
**Status:** Aceito
**Contexto:** Captação por carteira ([ADR-0084](ADR-0084-captacao-dia-carteira-agenda-cliente.md), [ADR-0086](ADR-0086-multiplas-carteiras-mesmo-faturamento-galpao.md))

## Contexto

Rotas de carregamento estavam ligadas ao **galpão** (`id_unidade_negocio_galpao`). Com múltiplas carteiras no mesmo galpão/faturamento, rotas de uma carteira não devem aparecer na captação de outra.

## Decisão

- `captacao_rotas.id_captacao_carteira` substitui `id_unidade_negocio_galpao`.
- Faturamento e galpão da rota são **derivados** da carteira (relacionamento `carteira.unidadeFaturamento` / `carteira.unidadeGalpao`).
- Pedido do lote só pode usar rota da **mesma carteira** do lote (`captacao_lotes.id_captacao_carteira`).
- Backfill na migration: rota existente → primeira carteira ativa com o mesmo galpão (menor `id`).

## Alternativas consideradas

- **Manter galpão na rota** — rejeitado; não separa carteiras no mesmo CD.
- **Rota N:N carteiras** — rejeitado; operação trata rota como pertencente a uma carteira comercial.

## Consequências

- CRUD de rotas filtra por carteira; permissão via galpão da carteira.
- [PLAN-0091](../plans/PLAN-0091-rotas-captacao-vinculo-carteira.md).
