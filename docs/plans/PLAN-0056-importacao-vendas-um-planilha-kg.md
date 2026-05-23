# PLAN-0056: Importação de vendas — UM da planilha em KG ou cadastrada

**ADR:** [ADR-0056](../decisions/ADR-0056-importacao-vendas-um-planilha-kg.md)
**Data:** 2026-05-22
**Status:** Concluído

## Objetivo

Aceitar quantidade em KG ou na UM cadastrada da fruta na importação de NF de vendas, convertendo corretamente para `qtd_fruta_um`.

## Pré-requisitos

- Importação de vendas ADR-0048 operacional
- Fruta com `kg_por_unidade_medicao` > 0

## Passos

1. **Domínio** — helper `VendaImportacaoQuantidade`.
2. **Processor** — substituir validação rígida de UM; gravar metadados da planilha.
3. **UI** — texto de ajuda e resumo com conversão quando aplicável.
4. **Testes** — unit helper + feature preview/confirmar com KG.

## Critério de conclusão

Planilha com `KG` para fruta em CAIXA passa na análise; confirmação grava `qtd_fruta_um` e `qtd_fruta_kg` coerentes; testes passando.

## Riscos

- KG insuficiente para formar 0,01 UM — linha rejeitada com mensagem explícita.
