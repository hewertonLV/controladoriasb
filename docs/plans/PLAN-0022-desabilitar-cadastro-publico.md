# PLAN-0022: Desabilitar cadastro público

**ADR:** [ADR-0022](../decisions/ADR-0022-desabilitar-cadastro-publico.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Impedir auto-cadastro; usuários criados apenas no admin.

## Critério de conclusão

`/register` retorna 404; login sem link Cadastre-se.
