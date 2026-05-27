# PLAN-0036: Importação de estoques por UM e preço total

**ADR:** [ADR-0036](../decisions/ADR-0036-importacao-estoques-um-e-preco-total.md)
**Data:** 2026-05-21
**Status:** Concluído

## Objetivo

Importar estoque inicial com planilha A–D (unidade, fruta, qtd UM, preço total) e derivar os demais campos do estoque.

## Pré-requisitos

- Frutas com `kg_por_unidade_medicao` > 0 cadastradas.
- Unidades com `possui_estoque` e `id_cigam` válidos.

## Passos

1. **Derivador** — classe `EstoqueImportacaoPosicaoDerivador` com fórmulas UM → kg e total → preço/kg.
2. **Processor** — ler colunas C/D como UM e preço total; enriquecer na resolução da fruta.
3. **UI** — textos e preview exibindo UM, total e valores derivados.
4. **Planilha modelo** — atualizar cabeçalhos em `planilhas/estoques_importacao.xlsx`.
5. **Testes** — unitário do derivador e feature do processor.

## Critério de conclusão

- Planilha com 4 colunas documentadas é processada sem exigir kg/R$/kg do usuário.
- Confirmação grava estoque com `qtd_fruta_um`, `valor_total_acumulado` e preços médios coerentes.

## Riscos

- Fruta sem kg/UM — já bloqueado no preview com mensagem clara.
