# PLAN-0096: Nome do motorista na aba Por rota

**ADR:** [ADR-0096](../decisions/ADR-0096-matriz-por-rota-nome-motorista.md)
**Data:** 2026-05-24
**Status:** Concluído

## Objetivo

Exibir e editar o nome do motorista ao lado da rota na aba Por rota; renomear coluna Ordem.

## Pré-requisitos

- Aba Por rota e agrupamento por rota (ADR-0094).

## Passos

1. Migration `nome_motorista` em `captacao_rotas`.
2. Serviço + endpoint PATCH motorista; incluir no snapshot.
3. UI input ao lado da rota + label da coluna.
4. Testes.

## Critério de conclusão

- Motorista persiste por rota; coluna renomeada; testes verdes.

## Riscos

- Poll sobrescrever input em edição — mitigação: não atualizar campo com foco.
