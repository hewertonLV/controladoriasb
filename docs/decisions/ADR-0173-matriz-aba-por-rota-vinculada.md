# ADR-0173: Aba dinâmica por rota vinculada na matriz

**Data:** 2026-05-28
**Status:** Aceito
**Contexto:** Matriz de captação — substituir aba única Por rota

## Contexto

A aba **Por rota** agrupava todas as rotas com lojas vinculadas numa única tela, aumentando scroll e misturando rotas distintas. A operação prefere navegar rota a rota.

## Decisão

- Remover a aba fixa **Por rota**.
- Criar **uma aba de navegação por rota** que possua ao menos uma loja com quantidade captada e rota vinculada (`grupos_ordem_carregamento`).
- Cada aba usa o **mesmo layout e ações** da antiga Por rota (motorista, veículo, ordem de carregamento, concluir/reabrir), filtrada à rota da aba.
- Parâmetro de URL: `aba=rota-{id_captacao_rota}`. `aba=por-rota` redireciona para a primeira rota vinculada (compatibilidade).
- Abas aparecem/desaparecem em tempo real via poll de `/matriz/estado`, sem refresh.

## Alternativas consideradas

- **Manter Por rota com filtro interno** — rejeitado; não reduz a card inicial nem isola rotas.
- **Sub-abas dentro de Por rota** — rejeitado; usuário pediu abas no mesmo nível de Captação/Rotas.

## Consequências

- [PLAN-0173](../plans/PLAN-0173-matriz-aba-por-rota-vinculada.md).
- Mensagens legadas que citam "aba Por rota" permanecem válidas como referência genérica às abas de rota.
