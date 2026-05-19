# PLAN-0013: ICMS por fruta, estado e operação

**ADR:** [ADR-0013](../decisions/ADR-0013-icms-fruta-por-estado-e-operacao.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Modelar ICMS em `fruta_icms`, remover colunas de `frutas` e adaptar cadastro, importação e movimentações.

## Passos

1. Migration `fruta_icms` + migração de dados + drop colunas em `frutas`.
2. Models, enum, factories e `FrutaIcmsCalculoService` / `FrutaIcmsSyncService`.
3. Atualizar `CompraMovimentacaoService` e `TransferenciaMovimentacaoService`.
4. Formulário admin de frutas (grid por estado).
5. Importação, auditoria, listagem, PDF e testes.

## Critério de conclusão

Sem colunas ICMS em `frutas`; compras CE com fornecedor PE usam valores de `fruta_icms`; testes de frutas e compras passam.
