# PLAN-0006: ICMS do abacaxi calculado por unidade (UM), não por KG

**ADR:** [ADR-0006](../decisions/ADR-0006-abacaxi-um-icms-por-unidade.md)
**Data:** 2026-05-17
**Status:** Concluído

## Objetivo

Garantir que os três produtos de abacaxi da planilha tenham `UM_ICMS = UM` conforme a legislação.

## Pré-requisitos

- Planilha `planilhas/MATERIAIS V2.xlsx` com coluna G (`UM_ICMS`) presente

## Passos

1. **Localizar os registros** — encontrar as linhas: `ABACAXI PEROLA`, `ABACAXI PREMIUM`, `ABACAXI TRADICIONAL`
2. **Verificar coluna G** — confirmar que `UM_ICMS = UM` (não `KG`) nesses três registros
3. **Verificar colunas E e F** — confirmar `ICMS_EX_COMPRA = 0.24` e `ICMS_NA_COMPRA = 0.12`
4. **Validar no sistema** — após importação, verificar se o campo `um_icms` do cadastro de fruta gravou `UM`

## Critério de conclusão

Os três registros de abacaxi importados no sistema com `um_icms = UM`, `icms_ex_compra = 0.24`, `icms_na_compra = 0.12`.

## Riscos

- Cálculo de ICMS incorreto no módulo de compras se `UM_ICMS` for ignorado — validar com o setor fiscal se o sistema aplica a base de cálculo por unidade corretamente
