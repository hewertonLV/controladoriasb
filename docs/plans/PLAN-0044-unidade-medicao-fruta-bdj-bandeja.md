# PLAN-0044: Unidade de medição BDJ (bandeja)

**ADR:** [ADR-0044](../decisions/ADR-0044-unidade-medicao-fruta-bdj-bandeja.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Permitir cadastrar e importar frutas com UM **BDJ** (bandeja).

## Pré-requisitos

- ADR-0044 aceita

## Passos

1. Adicionar `BDJ` em `FrutaUnidadeMedicao` com `rotulo()`.
2. Aliases em `FrutaPlanilhaNormalizer`.
3. Testes de cadastro e importação.

## Critério de conclusão

- Fruta salva com `unidade_medicao = BDJ`.
- Planilha com `BDJ` ou `BANDEJA` normaliza para `BDJ`.

## Riscos

- Nenhum impacto em banco (coluna string já existente).
