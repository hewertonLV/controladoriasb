# PLAN-0048: Importação de vendas (NF) por planilha

**ADR:** [ADR-0048](../decisions/ADR-0048-importacao-vendas-planilha-nf.md)
**Data:** 2026-05-19
**Status:** Concluído

## Objetivo

Botão e fluxo de importação de NF de vendas na tela Movimentação — Venda.

## Passos

1. Migration `venda_importacoes` + model + fila `vendas-importacao`.
2. `VendaImportacaoProcessor` + job preview.
3. Controller, rotas, permissões, view e botão no index.
4. Testes de preview e confirmação.

## Critério de conclusão

- Layout A–G documentado na tela; duplicidade com 7 campos; confirmação cria vendas agrupadas por NF/origem/cliente.
- Testes `VendaImportacaoTest` verdes.

## Riscos

- HUB sem faturamento na planilha — mitigado com erro explícito no preview.
