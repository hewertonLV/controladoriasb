# ADR-0100: Veículo exclusivo por rota na carteira (matriz)

**Data:** 2026-05-24
**Status:** Aceito
**Contexto:** Matriz de captação — aba Por rota ([ADR-0099](ADR-0099-matriz-por-rota-select-veiculo.md))

## Contexto

Na aba Por rota, o select listava todos os veículos ativos em cada rota. Ao vincular um veículo em uma rota, ele continuava aparecendo nas demais, permitindo duplicidade operacional.

## Decisão

- Na **mesma carteira**, cada veículo ativo pode estar vinculado a **no máximo uma rota** (`captacao_rotas.id_veiculo`).
- Selects da matriz exibem apenas veículos livres **ou** o veículo já vinculado à rota em edição.
- PATCH de veículo rejeita (`422`) tentativa de reutilizar veículo já vinculado a outra rota da carteira.

## Alternativas consideradas

- **Exclusividade só no front-end** — rejeitado; validação server-side é obrigatória.
- **Exclusividade global (todas as carteiras)** — rejeitado; escopo limitado à carteira do lote.

## Consequências

- Snapshot/poll mantém catálogo completo de veículos; filtro usa `rotas[].id_veiculo`.
- [PLAN-0100](../plans/PLAN-0100-matriz-veiculo-exclusivo-por-rota-carteira.md).
