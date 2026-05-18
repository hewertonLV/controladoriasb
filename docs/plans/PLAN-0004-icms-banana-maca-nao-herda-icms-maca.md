# PLAN-0004: "Banana Maçã" não herda ICMS de "Maçã"

**ADR:** [ADR-0004](../decisions/ADR-0004-icms-banana-maca-nao-herda-icms-maca.md)
**Data:** 2026-05-17
**Status:** Concluído

## Objetivo

Garantir que o produto `BANANA MAÇA 15KG F` tenha ICMS zerado, sem herdar os valores de Maçã.

## Pré-requisitos

- Planilha `planilhas/MATERIAIS V2.xlsx` com colunas E–H presentes

## Passos

1. **Localizar o registro** — encontrar a linha com `BANANA MAÇA 15KG F` na planilha
2. **Verificar colunas E e F** — confirmar que `ICMS_EX_COMPRA` e `ICMS_NA_COMPRA` estão com `0`
3. **Corrigir se necessário** — zerar os valores caso estejam preenchidos com 0,36/0,18 (valor de maçã)
4. **Documentar regra no script** — se houver script de preenchimento automático de ICMS, adicionar exclusão explícita: produtos começando com `BANANA` não herdam ICMS de `MAÇA/MAÇÃ`

## Critério de conclusão

`BANANA MAÇA 15KG F` com `ICMS_EX_COMPRA = 0`, `ICMS_NA_COMPRA = 0` e `UM_ICMS = KG`.

## Riscos

- Reprocessamento automático futuro pode re-aplicar o ICMS de maçã — a regra de exclusão deve estar no script de normalização, não apenas na planilha
