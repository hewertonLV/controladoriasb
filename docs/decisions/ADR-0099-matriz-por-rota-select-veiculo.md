# ADR-0099: Select de veículo na aba Por rota da matriz

**Data:** 2026-05-24
**Status:** Aceito
**Contexto:** Matriz de captação — aba Por rota ([ADR-0096](ADR-0096-matriz-por-rota-nome-motorista.md))

## Contexto

A operação informa motorista por rota na matriz. O cadastro de rotas já possui `id_veiculo`; na aba Por rota faltava vincular o veículo no fluxo de carregamento.

## Decisão

- Aba **Por rota**: abaixo do campo **Motorista**, exibir select com veículos **ativos** (`veiculos.status = ATIVO`), mesmo catálogo do cadastro de rotas.
- Persistência em `captacao_rotas.id_veiculo` via PATCH `/lotes/{lote}/rotas/{rota}/veiculo`.
- Opcional (valor vazio = sem veículo); mesmas regras de edição de vínculo de rota ([ADR-0098](ADR-0098-captacao-portao-quantidade-vinculo-rota-pos-vendas.md)).

## Alternativas consideradas

- **Veículo por loja/pedido** — rejeitado; rota já concentra logística de carregamento.
- **Filtrar veículos por unidade do galpão** — rejeitado por ora; alinhar ao cadastro de rotas (todos ativos).

## Consequências

- Snapshot da matriz inclui lista `veiculos` e `id_veiculo` por rota para poll.
- [PLAN-0099](../plans/PLAN-0099-matriz-por-rota-select-veiculo.md).
