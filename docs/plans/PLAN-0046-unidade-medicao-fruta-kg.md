# PLAN-0046: Unidade de medição KG (quilograma)

**ADR:** [ADR-0046](../decisions/ADR-0046-unidade-medicao-fruta-kg.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Cadastrar e importar frutas com UM **KG**.

## Passos

1. Enum `KG` + `rotulo()` + `casasDecimaisKg()`.
2. Aliases no `FrutaPlanilhaNormalizer`.
3. Ajuda no formulário admin.
4. Testes.

## Critério de conclusão

- Fruta com `unidade_medicao = KG` e planilha com coluna C = `KG` funcionam.
