# PLAN-0019: Layout da importação de estados com ID CIGAM

**ADR:** [ADR-0019](../decisions/ADR-0019-importacao-estados-colunas-id-cigam.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Importação e cadastro de estados com colunas A–D (id_cigam, nome, abreviação, descrição) e correspondência por id_cigam.

## Pré-requisitos

- Tabela `estados` e fluxo de importação (ADR-0018).

## Passos

1. Migration `id_cigam` + backfill — coluna única de 6 caracteres.
2. Normalizer e processor — ler A–D; match por `id_cigam`; validar sigla duplicada.
3. Model, requests, seeder, factory, confirmação HTTP e view de importação.
4. Testes de importação e CRUD.

## Critério de conclusão

Planilha de teste com id_cigam processa novas/atualizações; CRUD aceita id_cigam; testes passam.

## Riscos

- Planilhas antigas (layout A=nome) falham na validação — comunicar novo layout na tela de importação.
