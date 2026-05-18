# PLAN-0002: Status padrão "ATIVO" para todos os veículos na importação inicial

**ADR:** [ADR-0002](../decisions/ADR-0002-status-padrao-ativo-veiculos.md)
**Data:** 2026-05-17
**Status:** Concluído

## Objetivo

Garantir que todos os registros da planilha de veículos possuam a coluna `status` preenchida com `ATIVO`.

## Pré-requisitos

- Planilha `planilhas/veiculos.xlsx` com layout correto (colunas A–E)

## Passos

1. **Adicionar coluna E** — incluir coluna `status` na posição E da planilha (após id_cigam_unidade na coluna D)
2. **Preencher todos os registros** — definir o valor `ATIVO` em todas as linhas de dados da coluna E
3. **Validar** — confirmar que nenhuma célula da coluna E está vazia ou com valor diferente de `ATIVO`/`INATIVO`

## Critério de conclusão

Coluna E preenchida com `ATIVO` em todos os registros da planilha.

## Riscos

- Veículos inativos importados como ativos — após a carga, fazer levantamento com o operacional para identificar e inativar os veículos fora de uso
