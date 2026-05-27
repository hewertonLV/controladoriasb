# PLAN-0109: Centro de armazenagem na unidade de negócio

**ADR:** [ADR-0109](../decisions/ADR-0109-unidade-negocio-centro-armazenagem.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Cadastrar centro de armazenagem (3 dígitos) em cada unidade de negócio.

## Passos

1. Migration `centro_armazenagem`.
2. Model, validação, formulário, listagem, histórico.
3. Importação coluna M.
4. Testes `UnidadeNegocioTest`.

## Critério de conclusão

Campo obrigatório no cadastro; importação aceita coluna M; testes passam.
