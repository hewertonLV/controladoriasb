# PLAN-0082: Galpão operacional e emissão de NF

**ADR:** [ADR-0082](../decisions/ADR-0082-galpao-operacional-emite-nota-fiscal.md)
**Data:** 2026-05-29
**Status:** Concluído

## Objetivo

Permitir unidades galpão que também faturam NF (ex.: CD Barbalha).

## Passos

1. Migration `emite_nota_fiscal` + backfill galpões.
2. Model, factory, validação e formulário UN.
3. Venda, captação e filtros admin.
4. Testes.

## Critério de conclusão

CD Barbalha cadastrável como galpão + emite NF; Recife permanece galpão sem NF.
