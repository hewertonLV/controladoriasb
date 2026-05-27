# PLAN-0099: Select de veículo na aba Por rota da matriz

**ADR:** [ADR-0099](../decisions/ADR-0099-matriz-por-rota-select-veiculo.md)
**Data:** 2026-05-24
**Status:** Concluído

## Objetivo

Permitir vincular veículo por rota na aba Por rota da matriz, abaixo do motorista.

## Pré-requisitos

- Campo `captacao_rotas.id_veiculo` existente.
- Aba Por rota com motorista ([PLAN-0096](PLAN-0096-matriz-por-rota-nome-motorista.md)).

## Passos

1. **Service** — `veiculosDisponiveis()` e `atualizarVeiculoRota()`.
2. **HTTP** — PATCH veículo + request de validação.
3. **UI/JS** — select abaixo do motorista; poll sincroniza opções e valor.
4. **Testes** — feature salvar veículo e exibir na aba.

## Critério de conclusão

Select funcional na matriz; testes verdes.

## Riscos

- Lista grande de veículos — mitigação: mesmo padrão do cadastro de rotas; search-select futuro se necessário.
