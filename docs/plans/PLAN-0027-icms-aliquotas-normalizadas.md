# PLAN-0027: ICMS em `fruta_icms_aliquotas`

**ADR:** [ADR-0027](../decisions/ADR-0027-icms-aliquotas-normalizadas.md)
**Data:** 2026-05-20
**Status:** Concluído

## Objetivo

Substituir `fruta_icms` por alíquotas normalizadas, com venda internacional e cálculo por UF cliente vs faturamento.

## Passos

1. Enums + migrations + migração de dados
2. Models, sync, cálculo, histórico JSON
3. UI, importação, queries
4. Testes e remoção de código legado `FrutaIcms`

## Critério de conclusão

- CE entrada R$/kg nacional/internacional
- PE saída % com 4 combinações cadastráveis
- Venda usa procedência da fruta + escopo UF
- Testes verdes
