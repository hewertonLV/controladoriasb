# ADR-0006: ICMS do abacaxi calculado por unidade (UM), não por KG

**Data:** 2026-05-17  
**Status:** Aceito  
**Contexto:** Preenchimento de ICMS na planilha `planilhas/MATERIAIS V2.xlsx`

## Contexto

A Instrução Normativa SEFAZ Nº 80/2019 (CE) define o ICMS do abacaxi com unidade de medida `UN` (por unidade), com valor R$ 0,24 (exterior) e R$ 0,12 (nacional). Os produtos `ABACAXI PEROLA`, `ABACAXI PREMIUM` e `ABACAXI TRADICIONAL` são vendidos por unidade (`UN`) no sistema.

## Decisão

O campo `UM_ICMS` desses três produtos foi preenchido como `UM` (em vez de `KG`), seguindo a tabela legislativa.

## Alternativas consideradas

- Usar `KG` para todos os produtos uniformemente: rejeitado — contraria a legislação e potencialmente geraria base de cálculo incorreta.

## Consequências

- O importador (`FrutaPlanilhaNormalizer`) aceita `UM` como valor válido de `um_icms` (via enum `FrutaUmIcms`).
- O cálculo de ICMS por unidade deve ser considerado no módulo de compras ao precificar abacaxi.
