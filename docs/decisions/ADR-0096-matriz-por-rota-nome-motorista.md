# ADR-0096: Nome do motorista na aba Por rota

**Data:** 2026-05-24
**Status:** Aceito
**Contexto:** Matriz de captação — aba Por rota

## Contexto

A operação precisa informar o motorista responsável por cada rota de carregamento, visível ao lado do nome da rota na aba Por rota.

## Decisão

- Campo `captacao_rotas.nome_motorista` (string nullable, até 120 caracteres), **por rota** (não por loja/lote).
- Editável na aba **Por rota**, ao lado do nome da rota; persistido via PATCH na matriz.
- Coluna **Ordem** renomeada para **Ordem de Carregamento** na mesma aba.

## Alternativas consideradas

- **Motorista por pedido/loja** — rejeitado; carregamento é organizado por rota.
- **Reutilizar nome do veículo** — rejeitado; veículo e motorista são informações distintas.

## Consequências

- [PLAN-0096](../plans/PLAN-0096-matriz-por-rota-nome-motorista.md).
